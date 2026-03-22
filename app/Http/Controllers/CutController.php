<?php

namespace App\Http\Controllers;

use App\Models\CardPayment;
use App\Models\CreditCard;
use App\Models\Cut;
use App\Services\Fintrack\CutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CutController extends Controller
{
    public function __construct(
        private CutService $cuts,
    ) {}

    public function index(Request $request): Response
    {
        $cardIds = CreditCard::query()->where('user_id', $request->user()->id)->pluck('id');

        $cuts = Cut::query()
            ->whereIn('credit_card_id', $cardIds)
            ->with('creditCard')
            ->orderByDesc('period_end')
            ->paginate(20)
            ->through(function (Cut $cut) {
                $this->cuts->recalculateCutTotals($cut);
                $cut->refresh();
                $paid = (float) CardPayment::query()->where('cut_id', $cut->id)->sum('amount');
                $cut->setAttribute(
                    'remaining_balance',
                    max(0, round((float) $cut->total_accrued - $paid, 2))
                );

                return $cut;
            });

        return Inertia::render('Cuts/Index', [
            'cuts' => $cuts,
        ]);
    }

    public function show(Request $request, Cut $cut): Response
    {
        $this->authorizeCut($request, $cut);

        $this->cuts->recalculateCutTotals($cut);
        $cut->load(['creditCard', 'installments.purchase', 'payments']);

        $paid = (float) $cut->payments()->sum('amount');
        $remaining = max(0, round((float) $cut->total_accrued - $paid, 2));

        $card = $cut->creditCard;
        $pct = (float) ($card->minimum_payment_percent ?? 5);
        $suggestedMinimum = $remaining <= 0.01
            ? 0.0
            : min($remaining, max(round($remaining * ($pct / 100), 2), 0.01));

        return Inertia::render('Cuts/Show', [
            'cut' => $cut,
            'paid_total' => round($paid, 2),
            'remaining' => $remaining,
            'suggested_minimum' => round($suggestedMinimum, 2),
            'minimum_percent' => $pct,
            'payment_types' => [
                ['value' => 'minimo', 'label' => 'Pago mínimo'],
                ['value' => 'total', 'label' => 'Pago total del corte'],
                ['value' => 'abono', 'label' => 'Abono'],
            ],
        ]);
    }

    private function authorizeCut(Request $request, Cut $cut): void
    {
        $owns = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($cut->credit_card_id)
            ->exists();

        abort_unless($owns, 403);
    }
}
