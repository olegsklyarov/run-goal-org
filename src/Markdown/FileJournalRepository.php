<?php

declare(strict_types=1);

namespace RunOrg\Markdown;

use RunOrg\Domain\Date;
use RunOrg\Domain\Labels;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Number;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;
use RunOrg\Exception\InfrastructureError;
use RunOrg\Exception\UserError;
use RunOrg\Repository\JournalRepository;

final class FileJournalRepository implements JournalRepository
{
    public function __construct(
        private readonly string $dataDir,
        private readonly Parser $parser = new Parser(),
        private readonly Writer $writer = new Writer(),
    ) {
    }

    public function loadMonth(YearMonth $period): MonthJournal
    {
        $path = $this->monthPath($period);
        if (!is_file($path)) {
            throw new UserError("Не найден журнал {$path}");
        }

        $contents = $this->readFile($path);
        $front = $this->parser->parseFrontmatter($contents);
        if (!isset($front['period'], $front['target_km'])) {
            throw new UserError("В {$path} задайте period и target_km");
        }

        $filePeriod = YearMonth::fromString($front['period']);
        if (!$filePeriod->equals($period)) {
            throw new UserError("В {$path} period {$front['period']} не совпадает с {$period}");
        }

        $targetKm = Number::parsePositive($front['target_km'], 'Цель target_km');
        $rows = $this->parser->parseTable($contents, ['date', 'km']);
        $workouts = [];
        foreach ($rows as $row) {
            if ($row['date'] === '' && ($row['km'] ?? '') === '') {
                continue;
            }
            $date = Date::parse($row['date']);
            $km = Number::parsePositive($row['km'], 'Дистанция тренировки');
            $note = $row['note'] ?? '';
            $workouts[] = new Workout($date, $km, $note);
        }

        return new MonthJournal($period, $targetKm, $workouts);
    }

    public function saveMonth(MonthJournal $journal): void
    {
        $period = $journal->period();
        $title = sprintf(
            'Бег: %s %d',
            Labels::monthName($period->month()),
            $period->year()
        );
        $stem = sprintf('%02d', $period->month());
        $rows = [];
        foreach ($journal->workouts() as $workout) {
            $rows[] = [
                $workout->date()->format('Y-m-d'),
                Number::formatKm($workout->km()),
                $workout->note(),
            ];
        }

        $body = $this->writer->frontmatter([
            'period' => (string) $period,
            'target_km' => Number::formatKm($journal->targetKm()),
        ]);
        $body .= "\n\n# {$title}\n\n";
        $body .= $this->writer->table(['date', 'km', 'note'], $rows);
        $body .= "\n\n![График]({$stem}.svg)\n";

        $this->writeFile($this->monthPath($period), $body);
    }

    public function monthExists(YearMonth $period): bool
    {
        return is_file($this->monthPath($period));
    }

    public function loadYear(int $year): YearJournal
    {
        $path = $this->yearPath($year);
        if (!is_file($path)) {
            throw new UserError("Не найден журнал {$path}");
        }

        $contents = $this->readFile($path);
        $front = $this->parser->parseFrontmatter($contents);
        if (!isset($front['year'], $front['target_km'])) {
            throw new UserError("В {$path} задайте year и target_km");
        }

        $fileYear = YearMonth::fromYear($front['year']);
        if ($fileYear !== $year) {
            throw new UserError("В {$path} year {$front['year']} не совпадает с {$year}");
        }

        $targetKm = Number::parsePositive($front['target_km'], 'Цель target_km');

        return new YearJournal($year, $targetKm);
    }

    public function saveYear(YearJournal $journal): void
    {
        $year = $journal->year();
        $rows = [];
        foreach ($journal->monthlyTotals() as $month => $km) {
            $rows[] = [
                sprintf('%04d-%02d', $year, $month),
                $km === null ? '' : Number::formatKm($km),
            ];
        }

        $body = $this->writer->frontmatter([
            'year' => (string) $year,
            'target_km' => Number::formatKm($journal->targetKm()),
        ]);
        $body .= "\n\n# Бег: {$year}\n\n";
        $body .= "<!-- generated: monthly totals from 01.md … 12.md -->\n\n";
        $body .= $this->writer->table(['month', 'km'], $rows);
        $body .= "\n\n![График](year.svg)\n";

        $this->writeFile($this->yearPath($year), $body);
    }

    public function yearExists(int $year): bool
    {
        return is_file($this->yearPath($year));
    }

    public function loadMonthsOfYear(int $year): array
    {
        $journals = [];
        for ($month = 1; $month <= 12; $month++) {
            $period = new YearMonth($year, $month);
            if ($this->monthExists($period)) {
                $journals[] = $this->loadMonth($period);
            }
        }

        return $journals;
    }

    public function listYears(): array
    {
        $years = [];
        $entries = @scandir($this->dataDir);
        if ($entries === false) {
            throw new InfrastructureError("Не удалось прочитать каталог {$this->dataDir}");
        }
        foreach ($entries as $name) {
            if (preg_match('/^\d{4}$/', $name) && is_dir($this->dataDir . '/' . $name)) {
                $years[] = (int) $name;
            }
        }
        sort($years);

        return $years;
    }

    public function monthPath(YearMonth $period): string
    {
        return sprintf(
            '%s/%04d/%02d.md',
            $this->dataDir,
            $period->year(),
            $period->month()
        );
    }

    public function yearPath(int $year): string
    {
        return sprintf('%s/%04d/year.md', $this->dataDir, $year);
    }

    public function monthSvgPath(YearMonth $period): string
    {
        return sprintf(
            '%s/%04d/%02d.svg',
            $this->dataDir,
            $period->year(),
            $period->month()
        );
    }

    public function yearSvgPath(int $year): string
    {
        return sprintf('%s/%04d/year.svg', $this->dataDir, $year);
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InfrastructureError("Не удалось прочитать {$path}");
        }

        return $contents;
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new InfrastructureError("Не удалось создать каталог {$directory}");
        }

        $temporary = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($temporary, $contents) === false) {
            throw new InfrastructureError("Не удалось записать {$temporary}");
        }
        chmod($temporary, 0644);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new InfrastructureError("Не удалось сохранить {$path}");
        }
    }
}
