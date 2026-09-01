<?php

declare(strict_types=1);

namespace RunOrg\Application;

use RunOrg\Domain\Date;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Number;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearMonth;
use RunOrg\Exception\UserError;
use RunOrg\Repository\JournalRepository;

final class MigrateOrg
{
    public function __construct(
        private readonly string $dataDir,
        private readonly JournalRepository $repository,
        private readonly InitPeriod $init,
    ) {
    }

    /**
     * @return list<string>
     */
    public function handle(): array
    {
        $messages = [];
        $monthFiles = [];
        $yearFiles = [];

        foreach ($this->orgFiles() as $path) {
            $base = basename($path);
            if (preg_match('/^\d{4}\.org$/', $base)) {
                $yearFiles[] = $path;
            } else {
                $monthFiles[] = $path;
            }
        }

        foreach ($monthFiles as $path) {
            $journal = $this->convertMonth($path);
            $this->repository->saveMonth($journal);
            $messages[] = sprintf(
                'Месяц %s: %d тренировок → %s',
                $journal->period(),
                count($journal->workouts()),
                $this->relative($this->monthDestination($journal->period()))
            );
        }

        foreach ($yearFiles as $path) {
            $messages = array_merge($messages, $this->convertYear($path));
        }

        if ($messages === []) {
            throw new UserError("В {$this->dataDir} нет Org-журналов для миграции");
        }

        return $messages;
    }

    private function convertMonth(string $path): MonthJournal
    {
        $contents = $this->read($path);
        $config = $this->tableNamed($contents, 'running-config');
        if (count($config) !== 1) {
            throw new UserError("В running-config должна быть ровно одна строка данных: {$path}");
        }

        $values = array_values($config[0]);
        $period = YearMonth::fromString($config[0]['month'] ?? $values[0] ?? '');
        $targetKm = Number::parsePositive(
            (string) ($config[0]['target_km'] ?? $values[1] ?? ''),
            'Цель target_km'
        );

        $log = $this->tableNamed($contents, 'running-log');
        $workouts = [];
        foreach ($log as $row) {
            $dateValue = $row['date'] ?? array_values($row)[0] ?? '';
            $distanceValue = $row['distance'] ?? $row['km'] ?? array_values($row)[1] ?? '';
            if (trim((string) $dateValue) === '' && trim((string) $distanceValue) === '') {
                continue;
            }
            $workouts[] = new Workout(
                Date::parse((string) $dateValue),
                Number::parsePositive((string) $distanceValue, 'Дистанция тренировки')
            );
        }

        return new MonthJournal($period, $targetKm, $workouts);
    }

    /**
     * @return list<string>
     */
    private function convertYear(string $path): array
    {
        $contents = $this->read($path);
        $config = $this->tableNamed($contents, 'running-config');
        if (count($config) !== 1) {
            throw new UserError("В running-config должна быть ровно одна строка данных: {$path}");
        }

        $yearValue = $config[0]['year'] ?? array_values($config[0])[0] ?? '';
        $targetValue = $config[0]['target_km'] ?? array_values($config[0])[1] ?? '';
        $year = YearMonth::fromYear((string) $yearValue);
        $targetKm = Number::parsePositive((string) $targetValue, 'Цель target_km');

        $messages = [];
        if (!$this->repository->yearExists($year)) {
            $this->init->initYear($year, $targetKm);
            $messages[] = sprintf('Год %d → %d/year.md', $year, $year);
        }

        $months = $this->tableNamed($contents, 'running-months');
        foreach ($months as $row) {
            $monthValue = $row['month'] ?? array_values($row)[0] ?? '';
            $distanceValue = trim((string) ($row['distance'] ?? $row['km'] ?? array_values($row)[1] ?? ''));
            $period = YearMonth::fromString((string) $monthValue);
            if ($period->year() !== $year) {
                throw new UserError("Месяц {$monthValue} не относится к {$year} году");
            }
            if ($this->repository->monthExists($period)) {
                continue;
            }
            if ($distanceValue === '') {
                continue;
            }
            $km = Number::parsePositive($distanceValue, 'Дистанция за месяц');
            $workout = new Workout(
                $period->lastDay(),
                $km,
                'imported total'
            );
            $journal = new MonthJournal($period, $km, [$workout]);
            $this->repository->saveMonth($journal);
            $messages[] = sprintf(
                'Импорт %s: %s км на %s (note: imported total)',
                $period,
                Number::formatKm($km),
                $period->lastDay()->format('Y-m-d')
            );
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function orgFiles(): array
    {
        $files = glob($this->dataDir . '/[0-9][0-9][0-9][0-9]/*.org') ?: [];
        $result = [];
        foreach ($files as $path) {
            if (str_ends_with($path, '-data.org')) {
                continue;
            }
            $result[] = $path;
        }

        return $result;
    }

    /**
     * @return list<array<string, string>>
     */
    private function tableNamed(string $contents, string $name): array
    {
        if (!preg_match(
            '/^[ \t]*#\+name:[ \t]*' . preg_quote($name, '/') . '[ \t]*$/m',
            $contents,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            throw new UserError("Не найдена таблица {$name}");
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $rest = substr($contents, $offset);
        $lines = preg_split('/\R/', $rest);
        $tableLines = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                if ($tableLines === []) {
                    continue;
                }
                break;
            }
            if (!str_starts_with(ltrim($line), '|')) {
                break;
            }
            $tableLines[] = $line;
        }

        if ($tableLines === []) {
            throw new UserError("После #+name: {$name} нет Org-таблицы");
        }

        $rows = [];
        foreach ($tableLines as $line) {
            $cells = $this->splitOrgRow($line);
            if ($this->isOrgHline($cells)) {
                continue;
            }
            $rows[] = $cells;
        }

        if ($rows === []) {
            return [];
        }

        $header = $rows[0];
        $data = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = $rows[$i][$index] ?? '';
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function splitOrgRow(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        $parts = explode('|', $line);
        $cells = [];
        foreach ($parts as $part) {
            $cells[] = trim($part);
        }

        return $cells;
    }

    /**
     * @param list<string> $cells
     */
    private function isOrgHline(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '' && !preg_match('/^[-+]+$/', $cell)) {
                return false;
            }
        }

        return $cells !== [];
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new UserError("Не удалось прочитать {$path}");
        }

        return $contents;
    }

    private function monthDestination(YearMonth $period): string
    {
        return sprintf('%s/%04d/%02d.md', $this->dataDir, $period->year(), $period->month());
    }

    private function relative(string $path): string
    {
        $prefix = rtrim($this->dataDir, '/') . '/';
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }
}
