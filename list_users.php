<?php
use App\Models\User;
foreach(User::all() as $u) {
    echo "User: " . $u->id . " Name: " . $u->name . " Cards: " . $u->creditCards()->count() . PHP_EOL;
}
