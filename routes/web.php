<?php

use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CreditCardController;
use App\Http\Controllers\CutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseResponsibleController;
use App\Http\Controllers\ResponsiblePersonController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('credit-cards', CreditCardController::class)->except(['show']);
    Route::resource('responsible-people', ResponsiblePersonController::class)->except(['show']);
    Route::resource('purchases', PurchaseController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::post('/payments', [CardPaymentController::class, 'store'])->name('payments.store');
    Route::get('/cuts', [CutController::class, 'index'])->name('cuts.index');
    Route::get('/cuts/{cut}', [CutController::class, 'show'])->name('cuts.show');

    Route::patch(
        '/purchases/{purchase}/responsibles/{purchase_responsible}/pagado',
        [PurchaseResponsibleController::class, 'markPaid']
    )->name('purchase-responsibles.paid');
    Route::patch(
        '/purchases/{purchase}/responsibles/{purchase_responsible}/pendiente',
        [PurchaseResponsibleController::class, 'markPending']
    )->name('purchase-responsibles.pending');

    Route::post('/ai/chat', [\App\Http\Controllers\AiController::class, 'chat'])->name('ai.chat');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
