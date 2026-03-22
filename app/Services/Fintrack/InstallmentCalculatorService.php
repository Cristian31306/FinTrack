<?php

namespace App\Services\Fintrack;

class InstallmentCalculatorService
{
    /**
     * @return list<array{principal: float, interest: float, total: float}>
     */
    public function buildSchedule(float $principal, int $installments, float $annualEaPercent): array
    {
        if ($installments < 1) {
            return [];
        }

        if ($installments === 1) {
            return [[
                'principal' => round($principal, 2),
                'interest' => 0.0,
                'total' => round($principal, 2),
            ]];
        }

        $r = $this->monthlyRateFromEa($annualEaPercent);
        $n = $installments;
        $p = $principal;

        if ($r <= 0) {
            $each = round($p / $n, 2);
            $rows = [];
            for ($i = 0; $i < $n; $i++) {
                $rows[] = ['principal' => $each, 'interest' => 0.0, 'total' => $each];
            }
            $this->adjustRoundingDrift($rows, $p);

            return $rows;
        }

        $payment = $p * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
        $balance = $p;
        $rows = [];

        for ($i = 0; $i < $n; $i++) {
            if ($i === $n - 1) {
                $interest = round($balance * $r, 2);
                $principalPart = round($balance, 2);
                $rows[] = [
                    'principal' => $principalPart,
                    'interest' => $interest,
                    'total' => round($principalPart + $interest, 2),
                ];
                break;
            }

            $interest = $balance * $r;
            $principalPart = $payment - $interest;
            $balance -= $principalPart;
            $rows[] = [
                'principal' => round($principalPart, 2),
                'interest' => round($interest, 2),
                'total' => round($principalPart + $interest, 2),
            ];
        }

        $this->adjustRoundingDrift($rows, $p);

        return $rows;
    }

    private function monthlyRateFromEa(float $annualEaPercent): float
    {
        $ea = $annualEaPercent / 100;

        return pow(1 + $ea, 1 / 12) - 1;
    }

    /**
     * @param  list<array{principal: float, interest: float, total: float}>  $rows
     */
    private function adjustRoundingDrift(array &$rows, float $targetPrincipal): void
    {
        $sum = array_sum(array_column($rows, 'principal'));
        $drift = round($targetPrincipal - $sum, 2);
        if ($drift !== 0.0 && $rows !== []) {
            $last = count($rows) - 1;
            $rows[$last]['principal'] = round($rows[$last]['principal'] + $drift, 2);
            $rows[$last]['total'] = round($rows[$last]['principal'] + $rows[$last]['interest'], 2);
        }
    }
}
