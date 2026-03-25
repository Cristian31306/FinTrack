<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\CreditCard;
use App\Models\Cut;
use App\Services\Ai\AiAssistantService;

$user = User::find(2); // Usuario con tarjetas
$ai = app(AiAssistantService::class);

$card = CreditCard::where('user_id', $user->id)->first();
$cut = $card->cuts()->where('status', '!=', 'pagado')->first();
if (!$cut) {
    $cut = Cut::create([
        'credit_card_id' => $card->id,
        'period_start' => now()->subMonth(),
        'period_end' => now(),
        'total_accrued' => 50000,
        'status' => 'abierto'
    ]);
}

echo "--- PAYMENT TEST ---\n";
$res2 = $ai->chat($user, "Paga el último corte de mi tarjeta {$card->name}", [], null, true);
echo $res2 . "\n";

echo "--- CONFIRM PAYMENT ---\n";
// Ojo: el historial debe reflejar que acabamos de pedir el pago
$res3 = $ai->chat($user, "sí", [['role' => 'user', 'content' => "Paga el último corte de mi tarjeta {$card->name}"], ['role' => 'bot', 'content' => $res2]], null, true);
echo $res3 . "\n";

$cut->refresh();
if ($cut->status === 'pagado' || $cut->payments()->count() > 0) {
    echo "SUCCESS: Payment recorded and cut updated.\n";
} else {
    echo "FAILURE: Payment not detected.\n";
}
