<?php

namespace App\Services\Fintrack;

use App\Models\CreditCard;
use App\Models\Cut;
use App\Models\CardPayment;
use App\Models\PurchaseInstallment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CutService
{
    public function __construct(
        private StatementDateService $dates,
    ) {}

    public function ensureCutForStatementClose(CreditCard $card, CarbonInterface $statementClose): Cut
    {
        $close = CarbonImmutable::parse($statementClose)->startOfDay();
        [$periodStart, $periodEnd] = $this->dates->periodBoundsForStatementClose($close, $card->statement_day);

        // Buscar el corte existente ignorando las horas/formatos internos (soluciona UNIQUE constraints en SQLite)
        $existing = Cut::query()
            ->where('credit_card_id', $card->id)
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return Cut::create([
            'credit_card_id' => $card->id,
            'period_end' => $periodEnd->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'status' => 'abierto',
            'total_accrued' => 0,
        ]);
    }

    public function recalculateCutTotals(Cut $cut): void
    {
        $accrued = (float) PurchaseInstallment::query()
            ->where('cut_id', $cut->id)
            ->sum('total_amount');

        $paid = (float) CardPayment::query()
            ->where('cut_id', $cut->id)
            ->sum('amount');

        $newAccrued = round($accrued, 2);
        $newStatus = $this->deriveStatus($cut, $paid);

        // Solo guardar si hay cambios reales para evitar I/O innecesario
        if (abs((float) $cut->total_accrued - $newAccrued) > 0.001 || $cut->status !== $newStatus) {
            $cut->total_accrued = (string) $newAccrued;
            $cut->status = $newStatus;
            $cut->save();
        }
    }

    public function refreshCutsForCard(CreditCard $card): void
    {
        // Esta función se vacía o se limita porque causa Timeouts en el Dashboard
        // Los cortes se actualizan transaccionalmente cuando hay cambios en el sistema base.
    }

    private function deriveStatus(Cut $cut, float $paid): string
    {
        $accrued = (float) $cut->total_accrued;
        $today = CarbonImmutable::today();
        $periodEnd = CarbonImmutable::parse($cut->period_end)->startOfDay();

        if ($accrued <= 0.01) {
            return $periodEnd->lt($today) ? 'cerrado' : 'abierto';
        }

        if ($paid + 0.009 >= $accrued) {
            return 'pagado';
        }

        if ($periodEnd->lt($today)) {
            return 'cerrado';
        }

        return 'abierto';
    }
}
