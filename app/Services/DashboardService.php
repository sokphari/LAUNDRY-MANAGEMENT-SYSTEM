<?php

declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    /** @return array<string, mixed> Demonstration values until orders/payments are connected. */
    public static function sampleData(int $selectedYear): array
    {
        $currentYear = (int) (new \DateTimeImmutable('now'))->format('Y');
        $years = range($currentYear, $currentYear - 4);
        if (!in_array($selectedYear, $years, true)) {
            $selectedYear = $currentYear;
        }

        $monthlyAmounts = [420000, 560000, 510000, 730000, 680000, 940000, 820000, 760000, 880000, 690000, 920000, 860000];
        $yearlyRevenue = [];
        foreach ($monthlyAmounts as $index => $amount) {
            $yearlyRevenue[] = [
                'month' => date('M', mktime(0, 0, 0, $index + 1, 1, $selectedYear)),
                'amount' => $amount + (($selectedYear - $currentYear) * 12000),
            ];
        }

        $annualRevenue = array_sum(array_column($yearlyRevenue, 'amount'));

        return [
            'selectedYear' => $selectedYear,
            'years' => $years,
            'stats' => [
                ['label' => 'Total customers', 'value' => '128', 'note' => '+12 this month', 'tone' => 'violet'],
                ['label' => 'Revenue in ' . $selectedYear, 'value' => number_format($annualRevenue) . '៛', 'note' => 'Sample annual amount', 'tone' => 'emerald'],
                ['label' => 'Orders today', 'value' => '24', 'note' => 'Sample count', 'tone' => 'blue'],
                ['label' => 'Ready for pickup', 'value' => '8', 'note' => 'Sample count', 'tone' => 'amber'],
            ],
            'orderStatuses' => [
                ['label' => 'Received', 'value' => 16, 'color' => '#7C3AED'],
                ['label' => 'Processing', 'value' => 12, 'color' => '#A78BFA'],
                ['label' => 'Ready', 'value' => 8, 'color' => '#F59E0B'],
                ['label' => 'Collected', 'value' => 42, 'color' => '#10B981'],
                ['label' => 'Cancelled', 'value' => 3, 'color' => '#A1A1AA'],
            ],
            'yearlyRevenue' => $yearlyRevenue,
        ];
    }
}
