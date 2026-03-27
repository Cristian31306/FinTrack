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
        $userSharePending = 0.0;
        $cardSummaries = [];
        $upcomingCuts = [];

        foreach ($cards as $card) {
            $this->cutService->refreshCutsForCard($card);
            $cardFullDebt = 0.0;
            $cardPrincipalDebt = 0.0;

            $cuts = Cut::query()
                ->where('credit_card_id', $card->id)
                ->orderBy('period_end')
                ->get();

            foreach ($cuts as $cut) {
                // Cálculo de Totales (Capital + Interés)
                $accruedTotal = (float) PurchaseInstallment::query()->where('cut_id', $cut->id)->sum('total_amount');
                $paidTotal = (float) CardPayment::query()->where('cut_id', $cut->id)->sum('amount');
                $remainingTotal = max(0, round($accruedTotal - $paidTotal, 2));
                
                // Cálculo de Capital (Neto para uso de cupo)
                $accruedPrincipal = (float) PurchaseInstallment::query()->where('cut_id', $cut->id)->sum('principal_amount');
                // Asumimos que el pago amortiza proporcionalmente o primero al capital? 
                // Por simplicidad para el "Uso de Cupo", calculamos el % de capital restante.
                $ratio = $accruedTotal > 0.01 ? $remainingTotal / $accruedTotal : 0;
                $remainingPrincipal = round($accruedPrincipal * $ratio, 2);

                $cardFullDebt += $remainingTotal;
                $cardPrincipalDebt += $remainingPrincipal;

                // User share of this cut (proportional to remaining balance)
                if ($accruedTotal > 0.01) {
                    $ratio = $remainingTotal / $accruedTotal;
                    $installments = PurchaseInstallment::query()
                        ->where('cut_id', $cut->id)
                        ->with('purchase.purchaseResponsibles')
                        ->get();

                    foreach ($installments as $ins) {
                        $shares = $this->partiesForInstallmentShare($ins->purchase, (float) $ins->total_amount);
                        foreach ($shares as $s) {
                            if ($s['label'] === 'Yo') {
                                $userSharePending += $s['amount'] * $ratio;
                            }
                        }
                    }
                }

                if ($cut->status !== 'pagado' && $remainingTotal >= 0.01) {
                    $upcomingCuts[] = [
                        'cut_id' => $cut->id,
                        'card_id' => $card->id,
                        'card_name' => $card->name,
                        'card_last_4' => $card->last_4_digits,
                        'period_start' => $cut->period_start->format('Y-m-d'),
                        'period_end' => $cut->period_end->format('Y-m-d'),
                        'accrued' => $accruedTotal,
                        'remaining' => $remainingTotal,
                        'status' => $cut->status,
                        'status_label' => $this->cutStatusLabel($cut->status),
                        'payment_day' => $card->payment_day,
                        'periodo_en_curso' => ! $cut->period_end->lt(CarbonImmutable::today()),
                    ];
                }
            }

            $totalDebt += $cardFullDebt;

            $limit = (float) $card->credit_limit;
            $utilization = $limit > 0 ? min(100, round(($cardPrincipalDebt / $limit) * 100, 1)) : 0;

            $cardSummaries[] = [
                'id' => $card->id,
                'name' => $card->name,
                'franchise' => $card->franchise,
                'last_4_digits' => $card->last_4_digits,
                'credit_limit' => $limit,
                'available_credit' => max(0, round($limit - $cardPrincipalDebt, 2)),
                'annual_interest_ea' => (float) $card->annual_interest_ea,
                'debt' => round($cardPrincipalDebt, 2),
                'full_debt' => round($cardFullDebt, 2),
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

        $alerts = $this->buildAlerts($upcomingCuts);

        $spendingByCategory = Purchase::query()
            ->where('user_id', $user->id)
            ->whereMonth('purchase_date', now()->month)
            ->whereYear('purchase_date', now()->year)
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function ($purchases) {
                $cat = $purchases->first()->category;
                return [
                    'name' => $cat?->name ?? 'Sin Categoría',
                    'amount' => (float) $purchases->sum('total_amount'),
                    'color' => $cat?->color ?? '#94a3b8',
                    'icon' => $cat?->icon ?? 'Tag',
                ];
            })->values();

        return [
            'total_debt' => round($totalDebt, 2),
            'cards' => $cardSummaries,
            'upcoming_cuts' => $upcomingCuts,
            'user_share_pending' => round($userSharePending, 2),
            'alerts' => $alerts,
            'spending_by_category' => $spendingByCategory,
        ];
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
            $ratio = $row['accrued'] > 0 ? $row['remaining'] / $row['accrued'] : 0;

            foreach ($items as $ins) {
                /** @var \App\Models\Purchase $purchase */
                $purchase = $ins->purchase;
                $movements[] = [
                    'purchase_name' => $purchase->name,
                    'installment_label' => $ins->installment_number.'/'.$purchase->installments_count,
                    'amount' => round((float) $ins->total_amount * $ratio, 2),
                    'statement_close_date' => $ins->statement_close_date->format('Y-m-d'),
                    'parties' => $this->partiesForInstallmentShare($purchase, (float) $ins->total_amount * $ratio),
                ];
            }
            $row['movements'] = $movements;
            
            // Summarize by party for the entire cut
            $partySummary = [];
            foreach ($movements as $m) {
                foreach ($m['parties'] as $p) {
                    $lbl = $p['label'];
                    $partySummary[$lbl] = ($partySummary[$lbl] ?? 0.0) + $p['amount'];
                }
            }
            $row['summary_by_party'] = collect($partySummary)->map(fn($amt, $lbl) => [
                'label' => $lbl,
                'amount' => round($amt, 2),
            ])->values()->toArray();
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
