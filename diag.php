<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\CreditCard;
use App\Models\Category;

echo "--- FINTRACK DIAGNOSTIC ---\n";
foreach (User::all() as $u) {
    echo "USER [{$u->id}]: {$u->email} (Phone: {$u->phone_number})\n";
    echo "  Total Cards: " . $u->creditCards->count() . "\n";
    foreach ($u->creditCards as $c) {
        echo "    - [ID {$c->id}] {$c->name} ({$c->franchise})\n";
    }
    echo "  Total Categories: " . Category::where('user_id', $u->id)->count() . "\n";
}
echo "---------------------------\n";
