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
    $today = Date::parse('2026-09-01');
    $journal = new MonthJournal(YearMonth::fromString('2026-09'), 70.0);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertSame(30, $series->pointCount());
    foreach ($series->points() as $point) {
        assertSame(null, $point->actual());
        assertSame(false, $point->hasMarker());
    }
    assertFloat(75.0, $series->yMax());
    assertSame(true, $series->hasIdealPlan());
});

test('month without target has no ideal plan and y-max from actuals', function (): void {
    $today = Date::parse('2026-09-01');
    $period = YearMonth::fromString('2026-01');
    $journal = new MonthJournal($period, null, [
        new Workout(Date::parse('2026-01-31'), 21.66),
    ]);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertSame(null, $journal->targetKm());
    assertSame(false, $series->hasIdealPlan());
    assertFloat(21.66, $series->points()[30]->actual());
    assertFloat(30.0, $series->yMax());
});

test('several workouts on one date are summed', function (): void {
    $today = Date::parse('2026-09-01');
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-01'), 4.0),
        new Workout(Date::parse('2026-08-01'), 1.5),
        new Workout(Date::parse('2026-08-03'), 2.0),
    ]);
    $byDay = $journal->distancesByDay();
    assertFloat(5.5, $byDay[1]);
    assertFloat(2.0, $byDay[3]);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertFloat(5.5, $series->points()[0]->actual());
    assertFloat(5.5, $series->points()[1]->actual());
    assertFloat(7.5, $series->points()[2]->actual());
    assertFloat(7.5, $series->points()[3]->actual());
    assertSame(true, $series->points()[0]->hasMarker());
    assertSame(false, $series->points()[1]->hasMarker());
    assertSame(true, $series->points()[2]->hasMarker());
    assertSame(false, $series->points()[3]->hasMarker());
});

test('withWorkout inserts by date before a later entry', function (): void {
    $journal = new MonthJournal(YearMonth::fromString('2026-03'), 20.48, [
        new Workout(Date::parse('2026-03-31'), 20.48, 'imported total'),
    ]);
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-02'), 4.96));
    $workouts = $journal->workouts();
    assertSame(2, count($workouts));
    assertSame('2026-03-02', $workouts[0]->date()->format('Y-m-d'));
    assertFloat(4.96, $workouts[0]->km());
    assertSame('2026-03-31', $workouts[1]->date()->format('Y-m-d'));
    assertSame('imported total', $workouts[1]->note());
});

test('withWorkout keeps the month sorted for start, middle, end and same day', function (): void {
    $period = YearMonth::fromString('2026-03');
    $journal = new MonthJournal($period, 20.48);
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-15'), 5.0));
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-31'), 6.0));
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-01'), 1.0));
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-15'), 0.5));
    $journal = $journal->withWorkout(new Workout(Date::parse('2026-03-20'), 3.0));
    $dates = [];
    foreach ($journal->workouts() as $workout) {
        $dates[] = $workout->date()->format('Y-m-d');
    }
    assertSame([
        '2026-03-01',
        '2026-03-15',
        '2026-03-15',
        '2026-03-20',
        '2026-03-31',
    ], $dates);
    assertFloat(5.0, $journal->workouts()[1]->km());
    assertFloat(0.5, $journal->workouts()[2]->km());
});

test('workout outside the month is rejected', function (): void {
    expectUserError(function (): void {
        new MonthJournal(YearMonth::fromString('2026-08'), 112.0, [
            new Workout(Date::parse('2026-07-31'), 5.0),
        ]);
    });
});

test('past month extends horizontally to the last day', function (): void {
    $today = Date::parse('2026-09-01');
    $period = YearMonth::fromString('2026-01');
    $journal = new MonthJournal($period, null, [
        new Workout(Date::parse('2026-01-08'), 5.4),
        new Workout(Date::parse('2026-01-26'), 5.46),
    ]);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertFloat(5.4, $series->points()[7]->actual());
    assertFloat(5.4, $series->points()[24]->actual());
    assertFloat(10.86, $series->points()[25]->actual());
    assertFloat(10.86, $series->points()[30]->actual(), 'line continues to 31 January');
    assertSame(true, $series->points()[7]->hasMarker());
    assertSame(false, $series->points()[24]->hasMarker());
    assertSame(true, $series->points()[25]->hasMarker());
    assertSame(false, $series->points()[30]->hasMarker());
    assertSame(true, $series->points()[3]->isSunday());
});

test('current month stops at today', function (): void {
    $today = Date::parse('2026-08-15');
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-01'), 4.8),
        new Workout(Date::parse('2026-08-03'), 4.6),
        new Workout(Date::parse('2026-08-20'), 5.0),
    ]);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertFloat(4.8, $series->points()[0]->actual());
    assertFloat(9.4, $series->points()[14]->actual());
    assertSame(null, $series->points()[15]->actual());
    assertSame(null, $series->points()[19]->actual());
    assertSame(false, $series->points()[19]->hasMarker());
});

test('y-max rounds up with one extra tick', function (): void {
    $today = Date::parse('2026-09-01');
    $period = YearMonth::fromString('2026-08');
    $journal = new MonthJournal($period, 112.0, [
        new Workout(Date::parse('2026-08-30'), 113.4),
    ]);
    $series = BurnUpSeries::fromMonth($journal, $today);
    assertFloat(120.0, $series->yMax());
});

test('current year shows only closed months', function (): void {
    $today = Date::parse('2026-09-01');
    $series = BurnUpSeries::fromYear(2026, 600.0, [
        1 => 21.66,
        3 => 20.48,
        8 => 113.4,
    ], $today);
    assertFloat(21.66, $series->points()[0]->actual());
    assertFloat(21.66, $series->points()[1]->actual());
    assertFloat(42.14, $series->points()[2]->actual());
    assertFloat(155.54, $series->points()[7]->actual());
    assertSame(null, $series->points()[8]->actual(), 'September is not closed on 1 September');
    assertSame(true, $series->points()[0]->hasMarker());
    assertSame(false, $series->points()[1]->hasMarker());
    assertSame(true, $series->points()[7]->hasMarker());
    assertSame(false, $series->points()[8]->hasMarker());
    assertFloat(650.0, $series->yMax());
});

test('September appears on the year chart from 1 October', function (): void {
    $today = Date::parse('2026-10-01');
    $series = BurnUpSeries::fromYear(2026, 600.0, [
        8 => 113.4,
        9 => 10.0,
    ], $today);
    assertFloat(113.4, $series->points()[7]->actual());
    assertFloat(123.4, $series->points()[8]->actual());
    assertSame(null, $series->points()[9]->actual());
    assertSame(true, $series->points()[8]->hasMarker());
});

test('January of the current year is hidden until 1 February', function (): void {
    $today = Date::parse('2026-01-15');
    $series = BurnUpSeries::fromYear(2026, 600.0, [
        1 => 21.66,
    ], $today);
    foreach ($series->points() as $point) {
        assertSame(null, $point->actual());
    }
});

test('past year extends horizontally through December', function (): void {
    $today = Date::parse('2027-01-15');
    $series = BurnUpSeries::fromYear(2026, 600.0, [
        1 => 21.66,
        8 => 113.4,
    ], $today);
    assertFloat(135.06, $series->points()[11]->actual());
    assertSame(false, $series->points()[11]->hasMarker());
    assertSame(true, $series->points()[0]->hasMarker());
});

test('odd days get weekday plus day number in label', function (): void {
    $journal = new MonthJournal(YearMonth::fromString('2026-08'), 112.0);
    $series = BurnUpSeries::fromMonth($journal, Date::parse('2026-09-01'));
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
