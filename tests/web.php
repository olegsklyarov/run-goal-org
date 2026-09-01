<?php

declare(strict_types=1);

use RunOrg\Application\InitPeriod;
use RunOrg\Application\LogWorkout;
use RunOrg\Application\RenderChart;
use RunOrg\Domain\Date;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearMonth;
use RunOrg\Markdown\FileJournalRepository;
use RunOrg\Web\Application;
use RunOrg\Web\Request;

function webApp(string $dir, string $today = '2026-09-02'): Application
{
    $repository = new FileJournalRepository($dir);
    $charts = new RenderChart($repository, new SilentRenderer());

    return new Application(
        $repository,
        $charts,
        new LogWorkout($repository, $charts),
        Date::parse($today),
    );
}

function webJournal(string $dir): FileJournalRepository
{
    $repository = new FileJournalRepository($dir);
    (new InitPeriod($repository))->initYear(2026, 600);
    $repository->saveMonth(new MonthJournal(YearMonth::fromString('2026-08'), 112.0, [
        new Workout(Date::parse('2026-08-01'), 4.8, 'easy'),
    ]));
    $repository->saveMonth(new MonthJournal(YearMonth::fromString('2026-09'), 70.0));

    return $repository;
}

test('year page shows burn-up and months up to today', function (): void {
    $dir = tempDir();
    try {
        webJournal($dir);
        $response = webApp($dir)->handle(new Request('GET'));
        assertSame(200, $response->status());
        $body = $response->body();
        assertTrue(str_contains($body, 'Бег: 2026'));
        assertTrue(str_contains($body, 'svg=year'));
        assertTrue(str_contains($body, 'alt="График"'));
        assertTrue(str_contains($body, 'month=2026-08'));
        assertTrue(str_contains($body, 'month=2026-09'));
        assertTrue(!str_contains($body, 'month=2026-10'));
        assertTrue(str_contains($body, '4.8 км'));
        assertTrue(str_contains($body, 'Цель: 600 км'));
    } finally {
        removeDir($dir);
    }
});

test('month page shows form and workouts', function (): void {
    $dir = tempDir();
    try {
        webJournal($dir);
        $response = webApp($dir)->handle(new Request('GET', ['month' => '2026-08']));
        assertSame(200, $response->status());
        $body = $response->body();
        assertTrue(str_contains($body, 'Бег: август 2026'));
        assertTrue(str_contains($body, 'svg=2026-08'));
        assertTrue(str_contains($body, 'name="date"'));
        assertTrue(str_contains($body, 'name="km"'));
        assertTrue(str_contains($body, '2026-08-01'));
        assertTrue(str_contains($body, 'easy'));
        assertTrue(str_contains($body, 'value="2026-08-31"'));
    } finally {
        removeDir($dir);
    }
});

test('current month form defaults date to today', function (): void {
    $dir = tempDir();
    try {
        webJournal($dir);
        $response = webApp($dir)->handle(new Request('GET', ['month' => '2026-09']));
        assertSame(200, $response->status());
        assertTrue(str_contains($response->body(), 'value="2026-09-02"'));
        assertTrue(str_contains($response->body(), 'Пока нет тренировок'));
    } finally {
        removeDir($dir);
    }
});

test('POST adds a workout and redirects', function (): void {
    $dir = tempDir();
    try {
        $repository = webJournal($dir);
        $response = webApp($dir)->handle(new Request('POST', ['month' => '2026-09'], [
            'date' => '2026-09-02',
            'km' => '5.4',
            'note' => 'tempo',
        ]));
        assertSame(303, $response->status());
        assertSame('/?month=2026-09', $response->headers()['Location']);
        $journal = $repository->loadMonth(YearMonth::fromString('2026-09'));
        assertSame(1, count($journal->workouts()));
        assertSame('2026-09-02', $journal->workouts()[0]->date()->format('Y-m-d'));
        assertFloat(5.4, $journal->workouts()[0]->km());
        assertSame('tempo', $journal->workouts()[0]->note());
    } finally {
        removeDir($dir);
    }
});

test('future month is 404', function (): void {
    $dir = tempDir();
    try {
        webJournal($dir);
        $response = webApp($dir)->handle(new Request('GET', ['month' => '2026-10']));
        assertSame(404, $response->status());
    } finally {
        removeDir($dir);
    }
});

test('invalid km redisplays the month and does not change the journal', function (): void {
    $dir = tempDir();
    try {
        $repository = webJournal($dir);
        $period = YearMonth::fromString('2026-08');
        $before = count($repository->loadMonth($period)->workouts());
        $response = webApp($dir)->handle(new Request('POST', ['month' => '2026-08'], [
            'date' => '2026-08-01',
            'km' => '0',
            'note' => '',
        ]));
        assertSame(200, $response->status());
        assertTrue(str_contains($response->body(), 'должна быть больше нуля'));
        assertSame($before, count($repository->loadMonth($period)->workouts()));
    } finally {
        removeDir($dir);
    }
});

test('year SVG is served from the data directory', function (): void {
    $dir = tempDir();
    try {
        $repository = webJournal($dir);
        $path = $repository->yearSvgPath(2026);
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $response = webApp($dir)->handle(new Request('GET', ['svg' => 'year']));
        assertSame(200, $response->status());
        assertSame('image/svg+xml; charset=UTF-8', $response->headers()['Content-Type']);
        assertTrue(str_contains($response->body(), '<svg'));
    } finally {
        removeDir($dir);
    }
});
