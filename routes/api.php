<?php

use App\Http\Controllers\WhatsAppController;
use App\Models\CreditCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/usuario', function (Request $request) {
        return $request->user();
    });

    Route::get('/tarjetas', function (Request $request) {
        return CreditCard::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();
    });
});
