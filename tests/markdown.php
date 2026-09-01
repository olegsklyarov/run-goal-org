<?php

declare(strict_types=1);

use RunOrg\Application\InitPeriod;
use RunOrg\Application\RenderChart;
use RunOrg\Domain\Date;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Number;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearMonth;
use RunOrg\Markdown\FileJournalRepository;
use RunOrg\Markdown\Parser;
use RunOrg\Markdown\Writer;

test('markdown month round-trip preserves workouts and notes', function (): void {
    $dir = tempDir();
    try {
        $repository = new FileJournalRepository($dir);
        $period = YearMonth::fromString('2026-08');
        $original = new MonthJournal($period, 112.0, [
            new Workout(Date::parse('2026-08-01'), 4.8, 'easy'),
            new Workout(Date::parse('2026-08-08'), 7.4),
        ]);
        $repository->saveMonth($original);
        $loaded = $repository->loadMonth($period);
        assertSame('2026-08', (string) $loaded->period());
        assertFloat(112.0, $loaded->targetKm());
        assertSame(2, count($loaded->workouts()));
        assertSame('easy', $loaded->workouts()[0]->note());
        assertFloat(4.8, $loaded->workouts()[0]->km());
        assertSame('2026-08-08', $loaded->workouts()[1]->date()->format('Y-m-d'));
        $contents = file_get_contents($repository->monthPath($period));
        assertTrue(str_contains((string) $contents, 'period: 2026-08'));
        assertTrue(str_contains((string) $contents, '![График](08.svg)'));
    } finally {
        removeDir($dir);
    }
});

test('markdown month without target_km round-trips and omits the field', function (): void {
    $dir = tempDir();
    try {
        $repository = new FileJournalRepository($dir);
        $period = YearMonth::fromString('2026-01');
        $repository->saveMonth(new MonthJournal($period, null, [
            new Workout(Date::parse('2026-01-31'), 21.66, 'imported total'),
        ]));
        $loaded = $repository->loadMonth($period);
        assertSame(null, $loaded->targetKm());
        assertSame(1, count($loaded->workouts()));
        $contents = (string) file_get_contents($repository->monthPath($period));
        assertTrue(str_contains($contents, "period: 2026-01\n"));
        assertTrue(!str_contains($contents, 'target_km'));
    } finally {
        removeDir($dir);
    }
});

test('markdown year round-trip writes twelve months', function (): void {
    $dir = tempDir();
    try {
        $repository = new FileJournalRepository($dir);
        $init = new InitPeriod($repository);
        $init->initYear(2026, 600.0);
        $loaded = $repository->loadYear(2026);
        assertSame(2026, $loaded->year());
        assertFloat(600.0, $loaded->targetKm());
        $contents = file_get_contents($repository->yearPath(2026));
        assertTrue(str_contains((string) $contents, '<!-- generated: monthly totals from 01.md'));
        assertTrue(str_contains((string) $contents, '2026-12'));
    } finally {
        removeDir($dir);
    }
});

test('parser rejects missing required columns', function (): void {
    $parser = new Parser();
    $markdown = <<<'MD'
---
period: 2026-08
target_km: 70
---

| foo | bar |
| --- | --- |
| a   | b   |
MD;
    expectUserError(fn () => $parser->parseTable($markdown, ['date', 'km']));
});

test('writer aligns unicode cells', function (): void {
    $writer = new Writer();
    $table = $writer->table(['date', 'km', 'note'], [
        ['2026-08-01', '4.8', 'легко'],
    ]);
    assertTrue(str_starts_with($table, '| date'));
    assertTrue(str_contains($table, 'легко'));
});

test('init refuses to overwrite an existing month', function (): void {
    $dir = tempDir();
    try {
        $repository = new FileJournalRepository($dir);
        $init = new InitPeriod($repository);
        $init->initMonth(YearMonth::fromString('2026-09'), 70.0);
        expectUserError(fn () => $init->initMonth(YearMonth::fromString('2026-09'), 70.0));
    } finally {
        removeDir($dir);
    }
});

test('year aggregation uses month files as source of truth', function (): void {
    $dir = tempDir();
    try {
        $repository = new FileJournalRepository($dir);
        $init = new InitPeriod($repository);
        $init->initYear(2026, 600.0);
        $init->initMonth(YearMonth::fromString('2026-01'), 21.66);
        $january = $repository->loadMonth(YearMonth::fromString('2026-01'))
            ->withWorkout(new Workout(Date::parse('2026-01-31'), 21.66, 'imported total'));
        $repository->saveMonth($january);
        $init->initMonth(YearMonth::fromString('2026-08'), 112.0);
        $august = $repository->loadMonth(YearMonth::fromString('2026-08'))
            ->withWorkout(new Workout(Date::parse('2026-08-01'), 10.0))
            ->withWorkout(new Workout(Date::parse('2026-08-30'), 5.0));
        $repository->saveMonth($august);
        $init->initMonth(YearMonth::fromString('2026-09'), 70.0);

        $charts = new RenderChart($repository, new SilentRenderer());
        $year = $charts->aggregateYear(2026);
        $recorded = $year->recordedDistances();
        assertSame([1, 8], array_keys($recorded));
        assertFloat(21.66, $recorded[1]);
        assertFloat(15.0, $recorded[8]);
        assertTrue(!isset($recorded[9]), 'empty September must not extend the series');
    } finally {
        removeDir($dir);
    }
});

test('formatKm trims trailing zeros', function (): void {
    assertSame('5', Number::formatKm(5.0));
    assertSame('4.8', Number::formatKm(4.8));
    assertSame('21.66', Number::formatKm(21.66));
});
