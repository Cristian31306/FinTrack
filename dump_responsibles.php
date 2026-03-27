<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PurchaseResponsible;
use App\Models\Purchase;

$userId = 2; // Cristian
$responsibles = PurchaseResponsible::whereHas('purchase', fn($q) => $q->where('user_id', $userId))->with('responsiblePerson')->get();

echo "Responsibles found: " . $responsibles->count() . "\n";
foreach($responsibles as $r) {
    echo "Purchase: " . $r->purchase->id . " (" . $r->purchase->name . ") - Person: " . $r->responsiblePerson->name . " - Owed: " . $r->owed_amount . "\n";
}
