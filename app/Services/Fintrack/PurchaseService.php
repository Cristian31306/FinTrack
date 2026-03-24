<?php

namespace App\Services\Fintrack;

use App\Models\CreditCard;
use App\Models\Purchase;
use App\Models\PurchaseInstallment;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private InstallmentCalculatorService $calculator,
        private StatementDateService $dates,
        private CutService $cuts,
        private ResponsibleSplitService $splits,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{responsible_person_id: int, split_type: string, split_value: float}>|null  $responsibles
     */
    public function create(array $data, int $userId, ?array $responsibles = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId, $responsibles) {
            $card = CreditCard::query()
                ->where('user_id', $userId)
                ->findOrFail($data['credit_card_id']);

            $purchase = Purchase::query()->create([
                'user_id' => $userId,
                'credit_card_id' => $card->id,
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'total_amount' => $data['total_amount'],
                'installments_count' => $data['installments_count'],
                'purchase_date' => $data['purchase_date'],
            ]);

            $schedule = $this->calculator->buildSchedule(
                (float) $purchase->total_amount,
                (int) $purchase->installments_count,
                (float) $card->annual_interest_ea
            );

            foreach ($schedule as $idx => $row) {
                $num = $idx + 1;
                $close = $this->dates->statementCloseForInstallment(
                    $purchase->purchase_date,
                    (int) $card->statement_day,
                    $num
                );
                $cut = $this->cuts->ensureCutForStatementClose($card, $close);

                PurchaseInstallment::query()->create([
                    'purchase_id' => $purchase->id,
                    'cut_id' => $cut->id,
                    'installment_number' => $num,
                    'principal_amount' => $row['principal'],
                    'interest_amount' => $row['interest'],
                    'total_amount' => $row['total'],
                    'statement_close_date' => $close->toDateString(),
                ]);

                $this->cuts->recalculateCutTotals($cut);
            }

            if ($responsibles !== null && $responsibles !== []) {
                $this->splits->syncForPurchase($purchase, $responsibles, $userId);
            }

            return $purchase->load(['installments.cut', 'purchaseResponsibles.responsiblePerson', 'creditCard']);
        });
    }

    public function fullUpdate(Purchase $purchase, array $data, array $responsibles = []): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $responsibles) {
            // 1. Borrar cuotas viejas y recalcular sus cortes para dejar los saldos limpios
            $cutIds = $purchase->installments()->pluck('cut_id')->unique()->filter();
            $purchase->installments()->delete();
            $purchase->purchaseResponsibles()->delete(); // Borramos responsables viejos para rehacerlos

            foreach ($cutIds as $cutId) {
                $cut = \App\Models\Cut::query()->find($cutId);
                if ($cut) {
                    $this->cuts->recalculateCutTotals($cut);
                }
            }

            // 2. Actualizar las propiedades fundamentales (esto cambia todo el juego matemático)
            $purchase->fill([
                'credit_card_id' => $data['credit_card_id'],
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'total_amount' => $data['total_amount'],
                'installments_count' => $data['installments_count'],
                'purchase_date' => $data['purchase_date'],
            ]);
            $purchase->save();

            // 3. Reconstruir Amortización y Cuotas usando el core
            $this->buildSchedule($purchase);

            // 4. Reconstruir Responsables
            if (count($responsibles) > 0) {
                foreach ($responsibles as $r) {
                    $purchase->purchaseResponsibles()->create([
                        'responsible_person_id' => $r['responsible_person_id'],
                        'split_type' => $r['split_type'],
                        'split_value' => $r['split_value'],
                        'owed_amount' => $r['split_type'] === 'porcentaje'
                            ? ($data['total_amount'] * ($r['split_value'] / 100))
                            : $r['split_value'],
                        'status' => 'pendiente',
                    ]);
                }
            }

            return $purchase->load(['installments.cut', 'purchaseResponsibles.responsiblePerson', 'creditCard']);
        });
    }

    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $cutIds = $purchase->installments()->pluck('cut_id')->unique()->filter();
            $purchase->delete();
            foreach ($cutIds as $cutId) {
                $cut = \App\Models\Cut::query()->find($cutId);
                if ($cut) {
                    $this->cuts->recalculateCutTotals($cut);
                }
            }
        });
    }
}
