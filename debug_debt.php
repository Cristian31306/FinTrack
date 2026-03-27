<?php

use App\Models\User;
use App\Models\CreditCard;
use App\Models\Purchase;
use App\Models\PurchaseInstallment;
use App\Models\Cut;
use App\Services\Fintrack\DebtSummaryService;
use App\Services\Fintrack\CutService;
use App\Services\Fintrack\StatementDateService;
use Illuminate\Support\Facades\DB;

// Mock user
$user = User::find(2);
if (!$user) {
    echo "No user found\n";
    exit;
}

$summaryService = app(DebtSummaryService::class);

$data = $summaryService->dashboard($user);

echo "--- CORE CALCULATION REPLICATION ---\n";
$cards = CreditCard::query()->where('user_id', $user->id)->get();
foreach ($cards as $card) {
    echo "Card: {$card->name} (ID: {$card->id})\n";
    $cuts = Cut::query()->where('credit_card_id', $card->id)->get();
    foreach ($cuts as $cut) {
        $accrued = (float) PurchaseInstallment::query()->where('cut_id', $cut->id)->sum('total_amount');
        $paid = (float) CardPayment::query()->where('cut_id', $cut->id)->sum('amount');
        echo "  Cut ID: {$cut->id} (End: {$cut->period_end}) -> Accrued: $accrued, Paid: $paid, Rem: " . ($accrued - $paid) . "\n";
        
        $inst = PurchaseInstallment::query()->where('cut_id', $cut->id)->get();
        foreach ($inst as $i) {
            echo "    Installment ID: {$i->id} (Purchase: {$i->purchase_id}, Name: {$i->purchase->name}) -> Amount: {$i->total_amount}\n";
        }
    }
}
echo "--- END REPLICATION ---\n\n";

echo "Total Debt calculated by Service: " . $data['total_debt'] . "\n";
echo "User Share Pending: " . $data['user_share_pending'] . "\n";

foreach ($data['cards'] as $card) {
    echo "Card: {$card['name']} - Debt: {$card['debt']} - Limit: {$card['credit_limit']}\n";
}

if (!empty($data['upcoming_cuts'])) {
    foreach ($data['upcoming_cuts'] as $cut) {
        echo "Upcoming Cut: Card {$cut['card_name']} - Remaining: {$cut['remaining']}\n";
        if (isset($cut['summary_by_party'])) {
            foreach ($cut['summary_by_party'] as $party) {
                echo "  Party: {$party['label']} - Amount: {$party['amount']}\n";
            }
        }
    }
}
