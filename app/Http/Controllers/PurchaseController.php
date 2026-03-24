<?php

namespace App\Http\Controllers;

use App\Models\CreditCard;
use App\Models\Purchase;
use App\Models\ResponsiblePerson;
use App\Services\Fintrack\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchases,
    ) {}

    public function index(Request $request): Response
    {
        $list = Purchase::query()
            ->where('user_id', $request->user()->id)
            ->with(['creditCard', 'category', 'installments', 'purchaseResponsibles.responsiblePerson'])
            ->orderByDesc('purchase_date')
            ->paginate(15);

        return Inertia::render('Purchases/Index', [
            'purchases' => $list,
        ]);
    }

    public function create(Request $request): Response
    {
        $cards = CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name', 'last_4_digits']);

        $people = ResponsiblePerson::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        $categories = \App\Models\Category::whereNull('user_id')
            ->orWhere('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'creditCards' => $cards,
            'responsiblePeople' => $people,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credit_card_id' => ['required', 'exists:credit_cards,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:360'],
            'purchase_date' => ['required', 'date'],
            'responsibles' => ['nullable', 'array'],
            'responsibles.*.responsible_person_id' => ['required', 'integer', 'exists:responsible_people,id'],
            'responsibles.*.split_type' => ['required', 'in:porcentaje,monto'],
            'responsibles.*.split_value' => ['required', 'numeric', 'min:0'],
        ]);

        CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($validated['credit_card_id'])
            ->firstOrFail();

        $responsibles = $validated['responsibles'] ?? [];
        unset($validated['responsibles']);
        $responsibles = array_values(array_filter(
            $responsibles,
            fn ($r) => ! empty($r['responsible_person_id']) && $r['split_value'] !== '' && $r['split_value'] !== null,
        ));

        try {
            $this->purchases->create(
                $validated,
                $request->user()->id,
                $responsibles === [] ? null : $responsibles
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['responsibles' => $e->getMessage()]);
        }

        return redirect()->route('purchases.index')->with('success', 'Compra registrada y cuotas generadas.');
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        $this->authorizePurchase($request, $purchase);

        $purchase->load(['creditCard', 'installments.cut', 'purchaseResponsibles.responsiblePerson', 'category']);

        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase,
        ]);
    }
    public function edit(Request $request, Purchase $purchase): \Inertia\Response
    {
        $this->authorizePurchase($request, $purchase);

        $purchase->load(['purchaseResponsibles']);

        return Inertia::render('Purchases/Edit', [
            'purchase' => $purchase,
            'creditCards' => CreditCard::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(['id', 'name', 'last_4_digits']),
            'responsiblePeople' => ResponsiblePerson::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(),
            'categories' => \App\Models\Category::whereNull('user_id')
                ->orWhere('user_id', $request->user()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($request, $purchase);

        $validated = $request->validate([
            'credit_card_id' => ['required', 'exists:credit_cards,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installments_count' => ['required', 'integer', 'min:1', 'max:360'],
            'purchase_date' => ['required', 'date'],
            'responsibles' => ['nullable', 'array'],
            'responsibles.*.responsible_person_id' => ['required', 'exists:responsible_people,id'],
            'responsibles.*.split_type' => ['required', 'in:porcentaje,monto'],
            'responsibles.*.split_value' => ['required', 'numeric', 'min:0.01'],
        ]);

        $this->purchases->fullUpdate($purchase, $validated, $validated['responsibles'] ?? []);

        return redirect()->route('purchases.show', $purchase)->with('success', 'Compra actualizada estructuralmente. Se han recalculado sus cuotas, saldos y cortes.');
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizePurchase($request, $purchase);
        $this->purchases->delete($purchase);

        return redirect()->route('purchases.index')->with('success', 'Compra eliminada.');
    }

    private function authorizePurchase(Request $request, Purchase $purchase): void
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
    }
}
