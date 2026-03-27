<?php

use App\Models\Purchase;
use App\Models\PurchaseResponsible;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$p = Purchase::latest()->first();
if (!$p) {
    echo "No se encontró ninguna compra.\n";
    exit;
}

echo "🛒 Compra: {$p->name} (ID: {$p->id})\n";
echo "💰 Monto Total: {$p->total_amount}\n";

$responsibles = $p->purchaseResponsibles()->with('responsiblePerson')->get();

if ($responsibles->isEmpty()) {
    echo "❌ No hay responsables asociados.\n";
} else {
    echo "👥 Responsables:\n";
    foreach ($responsibles as $r) {
        echo "  - {$r->responsiblePerson->name}: \${$r->owed_amount} ({$r->split_type} {$r->split_value}%)\n";
    }
}
