<?php

declare(strict_types=1);

namespace RunOrg\Domain;

final class BurnUpSeries
{
    public const MONTH_Y_STEP = 5.0;
    public const YEAR_Y_STEP = 50.0;

    /**
     * @param list<BurnUpPoint> $points
     */
    public function __construct(
        private readonly array $points,
        private readonly float $targetKm,
        private readonly string $title,
        private readonly string $xLabel,
        private readonly float $yStep,
    ) {
    }

    public static function fromMonth(MonthJournal $journal): self
    {
        $period = $journal->period();
        $daysInMonth = $period->daysInMonth();
        $distances = $journal->distancesByDay();
        $lastActualDay = $distances === [] ? 0 : max(array_keys($distances));

        $points = [];
        $cumulative = 0.0;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $weekdayIndex = (int) $period->dateForDay($day)->format('w');
            $weekday = Labels::weekday($weekdayIndex);
            $label = $day % 2 === 1
                ? $weekday . '\\n' . $day
                : $weekday;
            $actual = null;
            if ($day <= $lastActualDay) {
                $cumulative += $distances[$day] ?? 0.0;
                $actual = $cumulative;
            }
            $points[] = new BurnUpPoint($day, $label, $actual, $weekdayIndex === 0);
        }

        $title = sprintf(
            'Бег: %s %d',
            Labels::monthName($period->month()),
            $period->year()
        );

        return new self(
            $points,
            $journal->targetKm(),
            $title,
            'День месяца',
            self::MONTH_Y_STEP
        );
    }

    /**
     * @param array<int, float> $distances month number => km
     */
    public static function fromYear(int $year, float $targetKm, array $distances): self
    {
        $lastActualMonth = $distances === [] ? 0 : max(array_keys($distances));

        $points = [];
        $cumulative = 0.0;
        for ($month = 1; $month <= 12; $month++) {
            $actual = null;
            if ($month <= $lastActualMonth) {
                $cumulative += $distances[$month] ?? 0.0;
                $actual = $cumulative;
            }
            $points[] = new BurnUpPoint(
                $month,
                Labels::monthAbbreviation($month),
                $actual,
                false
            );
        }

        return new self(
            $points,
            $targetKm,
            sprintf('Бег: %d', $year),
            'Месяц',
            self::YEAR_Y_STEP
        );
    }

    /**
     * @return list<BurnUpPoint>
     */
    public function points(): array
    {
        return $this->points;
    }

    public function targetKm(): float
    {
        return $this->targetKm;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function xLabel(): string
    {
        return $this->xLabel;
    }

    public function yStep(): float
    {
        return $this->yStep;
    }

    public function pointCount(): int
    {
        return count($this->points);
    }

    public function yMax(): float
    {
        $peak = $this->targetKm;
        foreach ($this->points as $point) {
            $actual = $point->actual();
            if ($actual !== null) {
                $peak = max($peak, $actual);
            }
        }

        $step = $this->yStep;

        return $step + $step * (int) ceil($peak / $step);
    }
}
