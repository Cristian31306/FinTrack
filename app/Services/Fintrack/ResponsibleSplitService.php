<?php

namespace App\Services\Fintrack;

use App\Models\Purchase;
use App\Models\PurchaseResponsible;
use App\Models\ResponsiblePerson;
use InvalidArgumentException;

class ResponsibleSplitService
{
    /**
     * @param  list<array{responsible_person_id: int, split_type: string, split_value: float}>  $rows
     * @return list<array{responsible_person_id: int, split_type: string, split_value: float, owed_amount: float}>
     */
    public function normalizeRows(Purchase $purchase, array $rows, int $userId): array
    {
        if ($rows === []) {
            return [];
        }

        $total = (float) $purchase->total_amount;
        $normalized = [];

        foreach ($rows as $row) {
            $personId = (int) $row['responsible_person_id'];
            $exists = ResponsiblePerson::query()
                ->where('id', $personId)
                ->where('user_id', $userId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException('Responsable inválido.');
            }

            $type = $row['split_type'];
            $value = (float) $row['split_value'];

            if ($type === 'porcentaje') {
                if ($value < 0 || $value > 100) {
                    throw new InvalidArgumentException('El porcentaje debe estar entre 0 y 100.');
                }
                $owed = round($total * ($value / 100), 2);
            } elseif ($type === 'monto') {
                if ($value < 0) {
                    throw new InvalidArgumentException('El monto no puede ser negativo.');
                }
                $owed = round($value, 2);
            } else {
                throw new InvalidArgumentException('Tipo de división inválido.');
            }

            $normalized[] = [
                'responsible_person_id' => $personId,
                'split_type' => $type,
                'split_value' => $value,
                'owed_amount' => $owed,
            ];
        }

        if ($normalized !== []) {
            $types = array_values(array_unique(array_column($normalized, 'split_type')));
            if (count($types) > 1) {
                throw new InvalidArgumentException('Mezcla porcentaje y monto en la misma compra no está permitida.');
            }

            if ($types[0] === 'porcentaje') {
                $sum = array_sum(array_column($normalized, 'split_value'));
                if (abs($sum - 100) > 0.05) {
                    throw new InvalidArgumentException('Los porcentajes deben sumar 100%.');
                }
            } else {
                $sumOwed = array_sum(array_column($normalized, 'owed_amount'));
                if ($sumOwed - $total > 0.05) {
                    throw new InvalidArgumentException('Los montos no pueden superar el total de la compra.');
                }
            }
        }

        return $normalized;
    }

    public function syncForPurchase(Purchase $purchase, array $rows, int $userId): void
    {
        $purchase->purchaseResponsibles()->delete();

        $normalized = $this->normalizeRows($purchase, $rows, $userId);

        foreach ($normalized as $r) {
            PurchaseResponsible::create([
                'purchase_id' => $purchase->id,
                'responsible_person_id' => $r['responsible_person_id'],
                'split_type' => $r['split_type'],
                'split_value' => $r['split_value'],
                'owed_amount' => $r['owed_amount'],
                'status' => 'pendiente',
            ]);
        }
    }
}
