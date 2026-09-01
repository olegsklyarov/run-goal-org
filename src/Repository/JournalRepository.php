<?php

declare(strict_types=1);

namespace RunOrg\Repository;

use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;

interface JournalRepository
{
    public function loadMonth(YearMonth $period): MonthJournal;

    public function saveMonth(MonthJournal $journal): void;

    public function monthExists(YearMonth $period): bool;

    public function loadYear(int $year): YearJournal;

    public function saveYear(YearJournal $journal): void;

    public function yearExists(int $year): bool;

    /**
     * @return list<MonthJournal>
     */
    public function loadMonthsOfYear(int $year): array;

    /**
     * @return list<int>
     */
    public function listYears(): array;
}
