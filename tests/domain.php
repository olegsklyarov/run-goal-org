<?php

declare(strict_types=1);

use RunOrg\Domain\BurnUpSeries;
use RunOrg\Domain\Date;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Number;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;

test('YearMonth parses YYYY-MM', function (): void {
    $period = YearMonth::fromString('2026-08');
    assertSame(2026, $period->year());
    assertSame(8, $period->month());
    assertSame(31, $period->daysInMonth());
    assertSame('2026-08', (string) $period);
});

test('YearMonth rejects invalid month', function (): void {
    expectUserError(fn () => YearMonth::fromString('2026-13'));
});

test('leap February has 29 days', function (): void {
    $period = YearMonth::fromString('2024-02');
    assertSame(29, $period->daysInMonth());
    $date = Date::parse('2024-02-29');
    assertSame('2024-02-29', $date->format('Y-m-d'));
});

test('non-leap February 29 is invalid', function (): void {
    expectUserError(fn () => Date::parse('2026-02-29'));
});

test('invalid date format is rejected', function (): void {
    expectUserError(fn () => Date::parse('08/01/2026'));
    expectUserError(fn () => Date::parse('2026-8-1'));
});

test('numbers reject comma and negatives', function (): void {
    expectUserError(fn () => Number::parseNonNegative('4,8', 'Дистанция'));
    expectUserError(fn () => Number::parseNonNegative('-1', 'Дистанция'));
    expectUserError(fn () => Number::parsePositive('0', 'Цель target_km'));
});

test('empty journal produces only NaN actuals', function (): void {
    $journal = new MonthJournal(YearMonth::fromString('2026-09'), 70.0);
    $series = BurnUpSeries::fromMonth($journal);
    assertSame(30, $series->pointCount());
    foreach ($series->points() as $point) {
        assertSame(null, $point->actual());
    }
    assertFloat(75.0, $series->yMax());
});

test('several workouts on one date are summed', function (): void {
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-01'), 4.0),
        new Workout(Date::parse('2026-08-01'), 1.5),
        new Workout(Date::parse('2026-08-03'), 2.0),
    ]);
    $byDay = $journal->distancesByDay();
    assertFloat(5.5, $byDay[1]);
    assertFloat(2.0, $byDay[3]);
    $series = BurnUpSeries::fromMonth($journal);
    assertFloat(5.5, $series->points()[0]->actual());
    assertFloat(5.5, $series->points()[1]->actual());
    assertFloat(7.5, $series->points()[2]->actual());
    assertSame(null, $series->points()[3]->actual());
});

test('workout outside the month is rejected', function (): void {
    expectUserError(function (): void {
        new MonthJournal(YearMonth::fromString('2026-08'), 112.0, [
            new Workout(Date::parse('2026-07-31'), 5.0),
        ]);
    });
});

test('cumulative stops at last logged day', function (): void {
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-01'), 4.8),
        new Workout(Date::parse('2026-08-03'), 4.6),
    ]);
    $series = BurnUpSeries::fromMonth($journal);
    assertFloat(4.8, $series->points()[0]->actual());
    assertFloat(4.8, $series->points()[1]->actual(), 'gap day still carries cumulative');
    assertFloat(9.4, $series->points()[2]->actual());
    assertSame(null, $series->points()[3]->actual());
    assertSame(true, $series->points()[1]->isSunday());
});

test('y-max rounds up with one extra tick', function (): void {
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-30'), 113.4),
    ]);
    $series = BurnUpSeries::fromMonth($journal);
    assertFloat(120.0, $series->yMax());
});

test('year series fills empty months before last record with zero', function (): void {
    $series = BurnUpSeries::fromYear(2026, 600.0, [
        1 => 21.66,
        3 => 20.48,
    ]);
    assertFloat(21.66, $series->points()[0]->actual());
    assertFloat(21.66, $series->points()[1]->actual());
    assertFloat(42.14, $series->points()[2]->actual());
    assertSame(null, $series->points()[3]->actual());
    assertFloat(650.0, $series->yMax());
});

test('odd days get weekday plus day number in label', function (): void {
    $journal = new MonthJournal(YearMonth::fromString('2026-08'), 112.0);
    $series = BurnUpSeries::fromMonth($journal);
    assertSame('Сб\\n1', $series->points()[0]->label());
    assertSame('Вс', $series->points()[1]->label());
});

test('YearJournal recorded distances skip empty months', function (): void {
    $journal = new YearJournal(2026, 600.0, [
        1 => 21.66,
        2 => null,
        8 => 113.4,
    ]);
    $recorded = $journal->recordedDistances();
    assertSame([1, 8], array_keys($recorded));
    assertFloat(135.06, $journal->totalKm());
});
