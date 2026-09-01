<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use RunOrg\Exception\UserError;

final class YearJournal
{
    /**
     * @param array<int, float|null> $monthlyTotals month number (1-12) => km or null
     */
    public function __construct(
        private readonly int $year,
        private readonly float $targetKm,
        private readonly array $monthlyTotals = [],
    ) {
        if ($year < 1 || $year > 9999) {
            throw new UserError("Год должен иметь формат YYYY: {$year}");
        }
        if ($targetKm <= 0.0) {
            throw new UserError('Цель target_km должна быть больше нуля');
        }
    }

    public function year(): int
    {
        return $this->year;
    }

    public function targetKm(): float
    {
        return $this->targetKm;
    }

    /**
     * @return array<int, float|null>
     */
    public function monthlyTotals(): array
    {
        $totals = [];
        for ($month = 1; $month <= 12; $month++) {
            $totals[$month] = $this->monthlyTotals[$month] ?? null;
        }

        return $totals;
    }

    /**
     * Months that have a recorded total, keyed by month number.
     *
     * @return array<int, float>
     */
    public function recordedDistances(): array
    {
        $distances = [];
        foreach ($this->monthlyTotals() as $month => $km) {
            if ($km !== null) {
                $distances[$month] = $km;
            }
        }

        return $distances;
    }

    public function totalKm(): float
    {
        $total = 0.0;
        foreach ($this->recordedDistances() as $km) {
            $total += $km;
        }

        return $total;
    }

    /**
     * @param array<int, float|null> $monthlyTotals
     */
    public function withMonthlyTotals(array $monthlyTotals): self
    {
        return new self($this->year, $this->targetKm, $monthlyTotals);
    }
}
