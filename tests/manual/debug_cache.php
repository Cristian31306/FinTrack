<?php

use App\Models\User;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\ResponsiblePerson;
use App\Services\Ai\WhatsAppBotService;
use Illuminate\Support\Facades\Cache;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = User::find(1);
$bot = app(WhatsAppBotService::class);

// Simular el estado prevío (Awaiting Responsibles)
$state = [
    'step' => 'awaiting_purchase_responsibles',
    'data' => [
        'name' => 'Gasto Test',
        'total_amount' => 100000,
        'purchase_date' => '2026-03-27',
        'category_id' => 1,
        'credit_card_id' => 1,
        'installments_count' => 1
    ]
];
Cache::put("whatsapp_bot_state_1", $state, now()->addMinutes(20));

echo "--- Iniciando Depuración de Caché ---\n";
echo "1. Llamando a handlePurchaseResponsibles con '1, 2'\n";

$bot->handle($u, "1, 2", "whatsapp:+123");

// Ver el nuevo estado
$stateAfter = Cache::get("whatsapp_bot_state_1");
echo "Estado después de responsables: " . json_encode($stateAfter['data']['responsibles'] ?? 'VACIO') . "\n";

// Ver el caché del asistente (donde executePurchase lee)
$assistantCacheKey = "fintrack_pending_purchase_1";
$assistantData = Cache::get($assistantCacheKey);
echo "Datos en el asistente (fintrack_pending_purchase_1): " . json_encode($assistantData['responsibles'] ?? 'NO EXISTE') . "\n";
