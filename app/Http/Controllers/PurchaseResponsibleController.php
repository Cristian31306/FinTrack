<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseResponsible;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseResponsibleController extends Controller
{
    public function markPaid(Request $request, Purchase $purchase, PurchaseResponsible $purchaseResponsible): RedirectResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
        abort_unless($purchaseResponsible->purchase_id === $purchase->id, 404);

        $purchaseResponsible->update([
            'status' => 'pagado',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Marcado como pagado.');
    }

    public function markPending(Request $request, Purchase $purchase, PurchaseResponsible $purchaseResponsible): RedirectResponse
    {
        abort_unless($purchase->user_id === $request->user()->id, 403);
        abort_unless($purchaseResponsible->purchase_id === $purchase->id, 404);

        $purchaseResponsible->update([
            'status' => 'pendiente',
            'paid_at' => null,
        ]);

        return back()->with('success', 'Marcado como pendiente.');
    }
}
