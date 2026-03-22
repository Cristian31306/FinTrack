<?php

namespace App\Services\Fintrack;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class StatementDateService
{
    public function firstStatementCloseOnOrAfter(CarbonInterface $from, int $statementDay): CarbonImmutable
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $day = min($statementDay, $from->daysInMonth);
        $candidate = CarbonImmutable::create($from->year, $from->month, $day);

        if ($candidate->lt($from)) {
            $candidate = $candidate->addMonth();
            $day = min($statementDay, $candidate->daysInMonth);
            $candidate = $candidate->startOfMonth()->day($day);
        }

        return $candidate;
    }

    public function statementCloseForInstallment(
        CarbonInterface $purchaseDate,
        int $statementDay,
        int $installmentNumber
    ): CarbonImmutable {
        $first = $this->firstStatementCloseOnOrAfter($purchaseDate, $statementDay);
        $first = $this->ensureStatementCloseOnOrAfterPurchase($first, $purchaseDate, $statementDay);

        if ($installmentNumber <= 1) {
            return $first;
        }

        $d = $first->addMonths($installmentNumber - 1);
        $day = min($statementDay, $d->daysInMonth);

        return $d->startOfMonth()->day($day);
    }

    /**
     * Garantiza que la fecha de cierre de estado de cuenta no quede antes del día de compra
     * (evita asignar cuotas a un corte ya pasado por desbordes de calendario).
     */
    public function ensureStatementCloseOnOrAfterPurchase(
        CarbonInterface $statementClose,
        CarbonInterface $purchaseDate,
        int $statementDay
    ): CarbonImmutable {
        $close = CarbonImmutable::parse($statementClose)->startOfDay();
        $purchase = CarbonImmutable::parse($purchaseDate)->startOfDay();
        $guard = 0;

        while ($close->lt($purchase) && $guard < 60) {
            $close = $close->addMonth();
            $day = min($statementDay, $close->daysInMonth);
            $close = $close->startOfMonth()->day($day);
            $guard++;
        }

        return $close;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodBoundsForStatementClose(CarbonInterface $statementClose, int $statementDay): array
    {
        $close = CarbonImmutable::parse($statementClose)->startOfDay();
        $prevMonth = $close->subMonth();
        $d = min($statementDay, $prevMonth->daysInMonth);
        $prevClose = $prevMonth->startOfMonth()->day($d);
        $periodStart = $prevClose->addDay();

        return [$periodStart, $close];
    }
}
