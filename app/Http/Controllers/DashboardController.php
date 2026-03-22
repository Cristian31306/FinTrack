<?php

namespace App\Http\Controllers;

use App\Services\Fintrack\DebtSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DebtSummaryService $summary): Response
    {
        $data = $summary->dashboard($request->user());

        return Inertia::render('Dashboard', $data);
    }
}
