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

        return Cut::firstOrCreate(
            [
                'credit_card_id' => $card->id,
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'period_start' => $periodStart->toDateString(),
                'status' => 'abierto',
                'total_accrued' => 0,
            ]
        );
    }

    public function recalculateCutTotals(Cut $cut): void
    {
        $accrued = (float) PurchaseInstallment::query()
            ->where('cut_id', $cut->id)
            ->sum('total_amount');

        $paid = (float) CardPayment::query()
            ->where('cut_id', $cut->id)
            ->sum('amount');

        $cut->total_accrued = round($accrued, 2);
        $cut->status = $this->deriveStatus($cut, $paid);
        $cut->save();
    }

    public function refreshCutsForCard(CreditCard $card): void
    {
        Cut::query()
            ->where('credit_card_id', $card->id)
            ->get()
            ->each(fn (Cut $cut) => $this->recalculateCutTotals($cut));
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
