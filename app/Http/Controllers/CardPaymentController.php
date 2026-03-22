<?php

namespace App\Http\Controllers;

use App\Models\CardPayment;
use App\Models\CreditCard;
use App\Models\Cut;
use App\Services\Fintrack\CutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CardPaymentController extends Controller
{
    public function __construct(
        private CutService $cuts,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credit_card_id' => ['required', 'exists:credit_cards,id'],
            'cut_id' => ['nullable', 'exists:cuts,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'in:minimo,total,abono'],
            'payment_date' => ['required', 'date'],
        ]);

        $card = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($validated['credit_card_id'])
            ->firstOrFail();

        if ($validated['cut_id'] !== null) {
            $cut = Cut::query()
                ->where('credit_card_id', $card->id)
                ->whereKey($validated['cut_id'])
                ->firstOrFail();
        } else {
            $cut = null;
        }

        CardPayment::query()->create([
            'user_id' => $request->user()->id,
            'credit_card_id' => $card->id,
            'cut_id' => $cut?->id,
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'payment_date' => $validated['payment_date'],
        ]);

        if ($cut) {
            $this->cuts->recalculateCutTotals($cut);

            return redirect()
                ->route('cuts.show', $cut)
                ->with('success', 'Pago registrado.');
        }

        return back()->with('success', 'Pago registrado.');
    }
}
