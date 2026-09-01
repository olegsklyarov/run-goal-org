<?php

declare(strict_types=1);

use RunOrg\Application\InitPeriod;
use RunOrg\Application\MigrateOrg;
use RunOrg\Cli\Args;
use RunOrg\Cli\Application;
use RunOrg\Cli\ExitCode;
use RunOrg\Config;
use RunOrg\Markdown\FileJournalRepository;

test('Args parses flags and positionals', function (): void {
    $args = Args::parse(['--date', '2026-08-01', '4.8', '--note', 'easy']);
    assertSame('2026-08-01', $args->option('date'));
    assertSame('easy', $args->option('note'));
    assertSame('4.8', $args->positionalAt(0));
});

test('CLI help exits 0', function (): void {
    $config = Config::load(dirname(__DIR__) . '/config.php');
    $app = Application::fromConfig($config);
    ob_start();
    $code = $app->run(['bin/run', '--help']);
    $output = ob_get_clean();
    assertSame(ExitCode::SUCCESS, $code);
    assertTrue(str_contains((string) $output, 'log [--date'));
});

test('CLI unknown command exits 1', function (): void {
    $config = Config::load(dirname(__DIR__) . '/config.php');
    $app = Application::fromConfig($config);
    ob_start();
    $code = $app->run(['bin/run', 'fly']);
    ob_end_clean();
    assertSame(ExitCode::USER_ERROR, $code);
});

test('migrate-org converts month logs and imports yearly totals', function (): void {
    $dir = tempDir();
    try {
        mkdir($dir . '/2026');
        foreach (['07-июль.org', '08-август.org', '09-сентябрь.org', '2026.org'] as $name) {
            $source = dirname(__DIR__) . '/tests/fixtures/2026/' . $name;
            if (!is_file($source)) {
                throw new RuntimeException("missing fixture {$source}");
            }
            copy($source, $dir . '/2026/' . $name);
        }
        $repository = new FileJournalRepository($dir);
        $migrate = new MigrateOrg($dir, $repository, new InitPeriod($repository));
        $messages = $migrate->handle();
        assertTrue($repository->monthExists(RunOrg\Domain\YearMonth::fromString('2026-08')));
        $august = $repository->loadMonth(RunOrg\Domain\YearMonth::fromString('2026-08'));
        assertSame(16, count($august->workouts()));
        assertFloat(112.0, $august->targetKm());
        $july = $repository->loadMonth(RunOrg\Domain\YearMonth::fromString('2026-07'));
        assertSame(12, count($july->workouts()));
        $september = $repository->loadMonth(RunOrg\Domain\YearMonth::fromString('2026-09'));
        assertSame(0, count($september->workouts()));
        $january = $repository->loadMonth(RunOrg\Domain\YearMonth::fromString('2026-01'));
        assertSame(1, count($january->workouts()));
        assertSame('imported total', $january->workouts()[0]->note());
        assertSame('2026-01-31', $january->workouts()[0]->date()->format('Y-m-d'));
        assertFloat(21.66, $january->workouts()[0]->km());
        assertTrue($repository->yearExists(2026));
        assertTrue(count($messages) > 0);
    } finally {
        removeDir($dir);
    }
});
