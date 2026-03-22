<?php

namespace App\Services\Fintrack;

use App\Models\CardPayment;
use App\Models\CreditCard;
use App\Models\Cut;
use App\Models\Purchase;
use App\Models\PurchaseInstallment;
use App\Models\User;
use Carbon\CarbonImmutable;

class DebtSummaryService
{
    public function __construct(
        private CutService $cutService,
    ) {}

    /**
     * @return array{
     *   total_debt: float,
     *   cards: list<array<string, mixed>>,
     *   upcoming_cuts: list<array<string, mixed>>,
     *   user_share_pending: float,
     *   alerts: list<array{type: string, message: string}>
     * }
     */
    public function dashboard(User $user): array
    {
        $cards = CreditCard::query()->where('user_id', $user->id)->get();
        $totalDebt = 0.0;
        $cardSummaries = [];
        $upcomingCuts = [];

        foreach ($cards as $card) {
            $this->cutService->refreshCutsForCard($card);
            $cardDebt = 0.0;

            $cuts = Cut::query()
                ->where('credit_card_id', $card->id)
                ->orderBy('period_end')
                ->get();

            foreach ($cuts as $cut) {
                $accrued = (float) PurchaseInstallment::query()->where('cut_id', $cut->id)->sum('total_amount');
                $paid = (float) CardPayment::query()->where('cut_id', $cut->id)->sum('amount');
                $remaining = max(0, round($accrued - $paid, 2));
                $cardDebt += $remaining;

                if ($cut->status !== 'pagado' && $remaining >= 0.01) {
                    $upcomingCuts[] = [
                        'cut_id' => $cut->id,
                        'card_id' => $card->id,
                        'card_name' => $card->name,
                        'card_last_4' => $card->last_4_digits,
                        'period_start' => $cut->period_start->format('Y-m-d'),
                        'period_end' => $cut->period_end->format('Y-m-d'),
                        'remaining' => $remaining,
                        'status' => $cut->status,
                        'status_label' => $this->cutStatusLabel($cut->status),
                        'payment_day' => $card->payment_day,
                        'periodo_en_curso' => ! $cut->period_end->lt(CarbonImmutable::today()),
                    ];
                }
            }

            $totalDebt += $cardDebt;

            $limit = (float) $card->credit_limit;
            $utilization = $limit > 0 ? min(100, round(($cardDebt / $limit) * 100, 1)) : 0;

            $cardSummaries[] = [
                'id' => $card->id,
                'name' => $card->name,
                'franchise' => $card->franchise,
                'last_4_digits' => $card->last_4_digits,
                'credit_limit' => $limit,
                'debt' => round($cardDebt, 2),
                'utilization_percent' => $utilization,
                'cupo_alert' => $utilization >= 80,
                'statement_day' => $card->statement_day,
                'payment_day' => $card->payment_day,
            ];
        }

        $today = CarbonImmutable::today();
        usort($upcomingCuts, function (array $a, array $b) use ($today): int {
            $aEnd = CarbonImmutable::parse($a['period_end'])->startOfDay();
            $bEnd = CarbonImmutable::parse($b['period_end'])->startOfDay();
            $aPast = $aEnd->lt($today);
            $bPast = $bEnd->lt($today);
            if ($aPast !== $bPast) {
                return $aPast ? -1 : 1;
            }

            return strcmp($a['period_end'], $b['period_end']);
        });

        if ($upcomingCuts !== []) {
            $chosen = $upcomingCuts[0];
            $periodEnd = CarbonImmutable::parse($chosen['period_end'])->startOfDay();
            $chosen['focus_context'] = $periodEnd->lt($today) ? 'corte_anterior' : 'proximo_cierre';
            $upcomingCuts = [$chosen];
        }

        $this->attachMovementsToUpcomingCuts($upcomingCuts);

        $userShare = $this->userSharePending($user->id);

        $alerts = $this->buildAlerts($upcomingCuts);

        return [
            'total_debt' => round($totalDebt, 2),
            'cards' => $cardSummaries,
            'upcoming_cuts' => $upcomingCuts,
            'user_share_pending' => round($userShare, 2),
            'alerts' => $alerts,
        ];
    }

    private function userSharePending(int $userId): float
    {
        $purchases = Purchase::query()
            ->where('user_id', $userId)
            ->with('purchaseResponsibles')
            ->get();

        $total = 0.0;
        foreach ($purchases as $p) {
            $others = (float) $p->purchaseResponsibles->sum('owed_amount');
            $total += max(0, round((float) $p->total_amount - $others, 2));
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $upcomingCuts
     * @return list<array{type: string, message: string}>
     */
    private function buildAlerts(array $upcomingCuts): array
    {
        $alerts = [];
        $today = CarbonImmutable::today();

        foreach ($upcomingCuts as $u) {
            $end = CarbonImmutable::parse($u['period_end']);
            $days = $today->diffInDays($end, false);
            if ($days >= 0 && $days <= 7 && $u['remaining'] > 0) {
                $endFmt = $end->format('d/m/Y');
                $remainingFmt = number_format((float) $u['remaining'], 2, ',', '.');
                $cardLabel = $u['card_name'];
                if (! empty($u['card_last_4'])) {
                    $cardLabel .= ' · •••• '.$u['card_last_4'];
                }
                $alerts[] = [
                    'type' => 'pago',
                    'message' => "Pago próximo: {$cardLabel} — cierre {$endFmt} (saldo aprox. {$remainingFmt}).",
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param  list<array<string, mixed>>  $upcomingCuts
     */
    private function attachMovementsToUpcomingCuts(array &$upcomingCuts): void
    {
        if ($upcomingCuts === []) {
            return;
        }

        $cutIds = array_column($upcomingCuts, 'cut_id');
        $byCut = PurchaseInstallment::query()
            ->whereIn('cut_id', $cutIds)
            ->with(['purchase.purchaseResponsibles.responsiblePerson'])
            ->orderBy('purchase_id')
            ->orderBy('installment_number')
            ->get()
            ->groupBy('cut_id');

        foreach ($upcomingCuts as &$row) {
            $items = $byCut->get($row['cut_id'], collect());
            $movements = [];
            foreach ($items as $ins) {
                /** @var \App\Models\Purchase $purchase */
                $purchase = $ins->purchase;
                $movements[] = [
                    'purchase_name' => $purchase->name,
                    'installment_label' => $ins->installment_number.'/'.$purchase->installments_count,
                    'amount' => round((float) $ins->total_amount, 2),
                    'statement_close_date' => $ins->statement_close_date->format('Y-m-d'),
                    'parties' => $this->partiesForInstallmentShare($purchase, (float) $ins->total_amount),
                ];
            }
            $row['movements'] = $movements;
        }
        unset($row);
    }

    /**
     * @return list<array{label: string, amount: float}>
     */
    private function partiesForInstallmentShare(Purchase $purchase, float $installmentTotal): array
    {
        $purchase->loadMissing('purchaseResponsibles.responsiblePerson');
        $total = (float) $purchase->total_amount;

        if ($purchase->purchaseResponsibles->isEmpty()) {
            return [['label' => 'Yo', 'amount' => round($installmentTotal, 2)]];
        }

        $parts = [];
        foreach ($purchase->purchaseResponsibles as $pr) {
            $owed = (float) $pr->owed_amount;
            $share = $total > 0.0001
                ? round($installmentTotal * ($owed / $total), 2)
                : 0.0;
            $parts[] = [
                'label' => $pr->responsiblePerson->name,
                'amount' => $share,
            ];
        }

        $assigned = round(array_sum(array_column($parts, 'amount')), 2);
        $remainder = round($installmentTotal - $assigned, 2);
        if (abs($remainder) >= 0.01) {
            $parts[] = ['label' => 'Yo', 'amount' => $remainder];
        }

        return $parts;
    }

    private function cutStatusLabel(string $status): string
    {
        return match ($status) {
            'pagado' => 'Pagado',
            'cerrado' => 'Cerrado al corte (pendiente)',
            default => 'En periodo',
        };
    }
}
