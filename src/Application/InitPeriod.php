<?php

declare(strict_types=1);

namespace RunOrg\Application;

use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;
use RunOrg\Exception\UserError;
use RunOrg\Repository\JournalRepository;

final class InitPeriod
{
    public function __construct(
        private readonly JournalRepository $repository,
    ) {
    }

    public function initMonth(YearMonth $period, float $targetKm): MonthJournal
    {
        if ($this->repository->monthExists($period)) {
            throw new UserError("Журнал {$period} уже существует");
        }

        $journal = new MonthJournal($period, $targetKm);
        $this->repository->saveMonth($journal);

        return $journal;
    }

    public function initYear(int $year, float $targetKm): YearJournal
    {
        if ($this->repository->yearExists($year)) {
            throw new UserError("Журнал {$year} уже существует");
        }

        $totals = [];
        for ($month = 1; $month <= 12; $month++) {
            $totals[$month] = null;
        }
        $journal = new YearJournal($year, $targetKm, $totals);
        $this->repository->saveYear($journal);

        return $journal;
    }
}
