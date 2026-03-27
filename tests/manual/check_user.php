<?php

use App\Models\User;
use App\Models\ResponsiblePerson;

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = User::where('phone_number', '+573045810485')->first();
if ($u) {
    echo "User ID: {$u->id}\n";
} else {
    echo "User not found\n";
}

$responsibles = ResponsiblePerson::all();
echo "ResponsiblePersons Count: " . count($responsibles) . "\n";
foreach ($responsibles as $r) {
    echo "  - ID: {$r->id}, Name: {$r->name}, UserID: {$r->user_id}\n";
}
