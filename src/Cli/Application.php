<?php

declare(strict_types=1);

namespace RunOrg\Cli;

use RunOrg\Application\InitPeriod;
use RunOrg\Application\LogWorkout;
use RunOrg\Application\MigrateOrg;
use RunOrg\Application\RenderChart;
use RunOrg\Application\ShowStatus;
use RunOrg\Chart\GnuplotRenderer;
use RunOrg\Config;
use RunOrg\Domain\Date;
use RunOrg\Domain\Number;
use RunOrg\Domain\YearMonth;
use RunOrg\Exception\InfrastructureError;
use RunOrg\Exception\UserError;
use RunOrg\Markdown\FileJournalRepository;
use Throwable;

final class Application
{
    public function __construct(
        private readonly FileJournalRepository $repository,
        private readonly RenderChart $charts,
        private readonly LogWorkout $logWorkout,
        private readonly InitPeriod $initPeriod,
        private readonly ShowStatus $showStatus,
        private readonly MigrateOrg $migrateOrg,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $repository = new FileJournalRepository($config->dataDir());
        $renderer = new GnuplotRenderer($config->gnuplot(), $config->gnuplotScript());
        $charts = new RenderChart($repository, $renderer);
        $init = new InitPeriod($repository);

        return new self(
            $repository,
            $charts,
            new LogWorkout($repository, $charts),
            $init,
            new ShowStatus($repository, $charts),
            new MigrateOrg($config->dataDir(), $repository, $init),
        );
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        try {
            $command = $argv[1] ?? 'help';
            $args = Args::parse(array_slice($argv, 2));
            if ($command === 'help' || $command === '-h' || $command === '--help' || $args->wantsHelp()) {
                echo $this->help();
                return ExitCode::SUCCESS;
            }

            $message = match ($command) {
                'log' => $this->log($args),
                'chart' => $this->chart($args),
                'init' => $this->init($args),
                'init-year' => $this->initYear($args),
                'status' => $this->status($args),
                'migrate-org' => $this->migrate(),
                default => throw new UserError(
                    "Неизвестная команда {$command}. php bin/run --help"
                ),
            };
            if ($message !== '') {
                echo $message . "\n";
            }

            return ExitCode::SUCCESS;
        } catch (UserError $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return ExitCode::USER_ERROR;
        } catch (InfrastructureError $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return ExitCode::INFRASTRUCTURE_ERROR;
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return ExitCode::INFRASTRUCTURE_ERROR;
        }
    }

    private function log(Args $args): string
    {
        $kmText = $args->positionalAt(0);
        if ($kmText === null) {
            throw new UserError('Укажите дистанцию: php bin/run log [--date YYYY-MM-DD] <km> [--note "..."]');
        }
        $km = Number::parsePositive($kmText, 'Дистанция');
        $dateText = $args->option('date');
        $date = $dateText === null ? Date::today() : Date::parse($dateText);
        $note = $args->option('note') ?? '';
        $journal = $this->logWorkout->handle($date, $km, $note);

        return sprintf(
            'Добавлено: %s — %s км',
            $date->format('Y-m-d'),
            Number::formatKm($km)
        ) . "\nГрафик: " . $this->repository->monthSvgPath($journal->period());
    }

    private function chart(Args $args): string
    {
        $target = $args->positionalAt(0);
        if ($target === null) {
            $period = YearMonth::fromDate(Date::today());
            $paths = [$this->charts->renderMonth($period)];
            if ($this->repository->yearExists($period->year())) {
                $paths[] = $this->charts->renderYear($period->year());
            }

            return $this->chartMessage($paths);
        }
        if ($target === 'all') {
            return $this->chartMessage($this->charts->renderAll());
        }
        if (preg_match('/^\d{4}$/', $target)) {
            return $this->chartMessage([$this->charts->renderYear((int) $target)]);
        }
        if (preg_match('/^\d{4}-\d{2}$/', $target)) {
            return $this->chartMessage([$this->charts->renderMonth(YearMonth::fromString($target))]);
        }

        throw new UserError('Аргумент chart: YYYY-MM, YYYY или all');
    }

    private function init(Args $args): string
    {
        $periodText = $args->positionalAt(0);
        if ($periodText === null) {
            throw new UserError('Укажите месяц: php bin/run init YYYY-MM --target <km>');
        }
        $target = Number::parsePositive(
            $args->requireOption('target', 'Укажите --target <km>'),
            'Цель target_km'
        );
        $period = YearMonth::fromString($periodText);
        $this->initPeriod->initMonth($period, $target);

        return "Создан журнал {$period}";
    }

    private function initYear(Args $args): string
    {
        $yearText = $args->positionalAt(0);
        if ($yearText === null) {
            throw new UserError('Укажите год: php bin/run init-year YYYY --target <km>');
        }
        $year = YearMonth::fromYear($yearText);
        $target = Number::parsePositive(
            $args->requireOption('target', 'Укажите --target <km>'),
            'Цель target_km'
        );
        $this->initPeriod->initYear($year, $target);

        return "Создан журнал {$year}";
    }

    private function status(Args $args): string
    {
        $target = $args->positionalAt(0);
        if ($target === null) {
            return $this->showStatus->forMonth(YearMonth::fromDate(Date::today()));
        }
        if (preg_match('/^\d{4}$/', $target)) {
            return $this->showStatus->forYear((int) $target);
        }
        if (preg_match('/^\d{4}-\d{2}$/', $target)) {
            return $this->showStatus->forMonth(YearMonth::fromString($target));
        }

        throw new UserError('Аргумент status: YYYY-MM или YYYY');
    }

    private function migrate(): string
    {
        return implode("\n", $this->migrateOrg->handle());
    }

    /**
     * @param list<string> $paths
     */
    private function chartMessage(array $paths): string
    {
        $lines = ['График обновлён:'];
        foreach ($paths as $path) {
            $lines[] = '  ' . $path;
        }

        return implode("\n", $lines);
    }

    private function help(): string
    {
        return <<<'HELP'
Журнал бега — Markdown + gnuplot

Использование:
  php bin/run <команда> [аргументы]

Команды:
  log [--date YYYY-MM-DD] <km> [--note "..."]
      Добавить тренировку и пересобрать графики месяца и года.

  chart [YYYY-MM|YYYY|all]
      Пересобрать SVG. Без аргумента — текущий месяц и его год.

  init YYYY-MM --target <km>
      Создать месячный журнал.

  init-year YYYY --target <km>
      Создать годовой журнал (цель года).

  status [YYYY-MM|YYYY]
      Цель, факт и остаток. Без аргумента — текущий месяц.

  migrate-org
      Конвертировать существующие *.org журналы в Markdown.

  help
      Показать эту справку.

HELP;
    }
}
