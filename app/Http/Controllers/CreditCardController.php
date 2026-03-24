<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditCardController extends Controller
{
    public function index(Request $request): Response
    {
        $cards = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('CreditCards/Index', [
            'creditCards' => $cards,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CreditCards/Create', [
            'franchises' => ['Visa', 'Mastercard', 'American Express', 'Diners', 'Otro'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'franchise' => ['required', 'string', 'max:64'],
            'last_4_digits' => ['nullable', 'digits:4'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'annual_interest_ea' => ['required', 'numeric', 'min:0'],
            'statement_day' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'color' => ['required', 'string', 'max:20'],
        ]);
        if (($validated['last_4_digits'] ?? '') === '') {
            $validated['last_4_digits'] = null;
        }

        $request->user()->creditCards()->create($validated);

        return redirect()->route('credit-cards.index')->with('success', 'Tarjeta creada.');
    }

    public function edit(Request $request, CreditCard $creditCard): Response
    {
        $this->authorizeCard($request, $creditCard);

        return Inertia::render('CreditCards/Edit', [
            'creditCard' => $creditCard,
            'franchises' => ['Visa', 'Mastercard', 'American Express', 'Diners', 'Otro'],
        ]);
    }

    public function update(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $this->authorizeCard($request, $creditCard);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'franchise' => ['required', 'string', 'max:64'],
            'last_4_digits' => ['nullable', 'digits:4'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'annual_interest_ea' => ['required', 'numeric', 'min:0'],
            'statement_day' => ['required', 'integer', 'min:1', 'max:31'],
            'payment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'color' => ['required', 'string', 'max:20'],
        ]);
        if (($validated['last_4_digits'] ?? '') === '') {
            $validated['last_4_digits'] = null;
        }

        $creditCard->update($validated);

        return redirect()->route('credit-cards.index')->with('success', 'Tarjeta actualizada.');
    }

    public function destroy(Request $request, CreditCard $creditCard): RedirectResponse
    {
        $this->authorizeCard($request, $creditCard);
        $creditCard->delete();

        return redirect()->route('credit-cards.index')->with('success', 'Tarjeta eliminada.');
    }

    private function authorizeCard(Request $request, CreditCard $creditCard): void
    {
        abort_unless($creditCard->user_id === $request->user()->id, 403);
    }
}
