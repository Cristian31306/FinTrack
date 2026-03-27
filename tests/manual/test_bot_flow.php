<?php

use App\Models\User;
use App\Models\Category;
use App\Models\CreditCard;
use App\Services\Ai\WhatsAppBotService;
use Illuminate\Support\Facades\Config;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Config::set('logging.default', 'null');

$user = User::where('phone_number', '+573045810485')->first() ?? User::whereNotNull('phone_number')->first();
if (!$user) { exit("No user found\n"); }

$bot = app(WhatsAppBotService::class);

echo "--- Probando Bot de WhatsApp (Flujo Completo con Responsables) ---\n";

$steps = [
    ['msg' => 'hola', 'desc' => 'Menú'],
    ['msg' => '1', 'desc' => 'Registrar'],
    ['msg' => 'Cena con Amigos', 'desc' => 'Nombre'],
    ['msg' => '120000', 'desc' => 'Monto'],
    ['msg' => '1', 'desc' => 'Fecha Hoy'],
    ['msg' => '1', 'desc' => 'Categoría Comida'],
    ['msg' => '1', 'desc' => 'Tarjeta Nu'],
    ['msg' => '1', 'desc' => '1 Cuota'],
    ['msg' => '2', 'desc' => 'Dividir Gasto? SÍ'],
    ['msg' => '1, 2', 'desc' => 'Responsables 1 y 2'],
    ['msg' => 'Si', 'desc' => 'Confirmación Final'],
];

foreach ($steps as $step) {
    echo "> {$step['msg']} ({$step['desc']})\n";
    try {
        $res = $bot->handle($user, $step['msg'], 'whatsapp:' . $user->phone_number);
        printTextResponse($res);
    } catch (\Throwable $e) { echo "[ERROR] " . $e->getMessage() . "\n"; }
    echo "\n";
}

function printTextResponse($res) {
    if (is_array($res)) {
        echo "[BOT] " . ($res['text'] ?? 'Array sin texto') . "\n";
    } else {
        echo "[BOT] " . ($res ?? 'NULL') . "\n";
    }
}
