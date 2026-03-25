<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Category;
use App\Services\Ai\AiAssistantService;
use Illuminate\Support\Facades\Cache;

$user = User::first();
$ai = app(AiAssistantService::class);

echo "--- STEP 1: PREPARE ---\n";
$ai->chat($user, "Crea una categoría de Mascotas con un icono de perro", [], null, true);

echo "--- STEP 2: CONFIRM ---\n";
// Simular que el usuario dice "sí"
$res = $ai->chat($user, "sí", [['role' => 'user', 'content' => 'Crea una categoría de Mascotas con un icono de perro'], ['role' => 'bot', 'content' => 'Vista previa de nueva categoría...']], null, true);
echo $res . "\n";

$cat = Category::where('name', 'Mascotas')->first();
if ($cat) {
    echo "SUCCESS: Category '{$cat->name}' created with icon {$cat->icon}\n";
    $cat->delete();
} else {
    echo "FAILURE: Category not found\n";
}
