<?php

declare(strict_types=1);

namespace RunOrg\Application;

use DateTimeImmutable;
use RunOrg\Domain\Date;
use RunOrg\Domain\Labels;
use RunOrg\Domain\Number;
use RunOrg\Domain\YearMonth;
use RunOrg\Repository\JournalRepository;

final class ShowStatus
{
    public function __construct(
        private readonly JournalRepository $repository,
        private readonly RenderChart $charts,
    ) {
    }

    public function forMonth(YearMonth $period, ?DateTimeImmutable $today = null): string
    {
        $today ??= Date::today();
        $journal = $this->repository->loadMonth($period);
        $total = $journal->totalKm();
        $target = $journal->targetKm();
        $daysLeft = $this->daysLeftInMonth($period, $today);
        $title = sprintf(
            'Бег: %s %d',
            Labels::monthName($period->month()),
            $period->year()
        );

        $lines = [
            $title,
            $target === null
                ? 'Цель:     не задана'
                : sprintf('Цель:     %s км', Number::formatKm($target)),
            sprintf('Факт:     %s км', Number::formatKm($total)),
        ];
        if ($target !== null) {
            $lines[] = sprintf('Осталось: %s км', Number::formatKm($target - $total));
        }
        $lines[] = sprintf('Дней до конца месяца: %d', $daysLeft);
        $lines[] = sprintf('Тренировок: %d', count($journal->workouts()));

        return implode("\n", $lines);
    }

    public function forYear(int $year): string
    {
        $journal = $this->charts->aggregateYear($year);
        $total = $journal->totalKm();
        $target = $journal->targetKm();
        $recorded = count($journal->recordedDistances());

        return implode("\n", [
            sprintf('Бег: %d', $year),
            sprintf('Цель:     %s км', Number::formatKm($target)),
            sprintf('Факт:     %s км', Number::formatKm($total)),
            sprintf('Осталось: %s км', Number::formatKm($target - $total)),
            sprintf('Месяцев с данными: %d', $recorded),
        ]);
    }

    private function daysLeftInMonth(YearMonth $period, DateTimeImmutable $today): int
    {
        $todayPeriod = YearMonth::fromDate($today);
        if ($period->year() < $todayPeriod->year()
            || ($period->year() === $todayPeriod->year() && $period->month() < $todayPeriod->month())
        ) {
            return 0;
        }
        if ($period->year() > $todayPeriod->year()
            || ($period->year() === $todayPeriod->year() && $period->month() > $todayPeriod->month())
        ) {
            return $period->daysInMonth();
        }

        $remaining = $period->daysInMonth() - (int) $today->format('j');

        return max(0, $remaining);
    }
}
