<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\Ai\WhatsAppBotService;
use Illuminate\Support\Facades\Cache;

$user = User::first();
$bot = app(WhatsAppBotService::class);
$from = "whatsapp:" . $user->phone_number;

Cache::forget('whatsapp_bot_state_' . $user->id);

function testStep($bot, $user, $from, $msg) {
    echo "USER: $msg\n";
    $res = $bot->handle($user, $msg, $from);
    $text = is_array($res) ? $res['text'] : $res;
    echo "BOT: $text\n";
    if (is_array($res) && isset($res['buttons'])) {
        echo "BUTTONS: [" . implode(", ", $res['buttons']) . "]\n";
    }
    echo "-----------------------------------\n";
}

echo "--- INICIANDO PRUEBA DE BOT ESTRUCTURADO ---\n";

testStep($bot, $user, $from, "1"); // Registrar Gasto de nuevo para probar flujo
testStep($bot, $user, $from, "Netflix");
testStep($bot, $user, $from, "45000");

testStep($bot, $user, $from, "resumen");

echo "--- FIN DE LA PRUEBA ---\n";
