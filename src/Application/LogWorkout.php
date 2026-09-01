<?php

declare(strict_types=1);

namespace RunOrg\Application;

use DateTimeImmutable;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Workout;
use RunOrg\Domain\YearMonth;
use RunOrg\Exception\UserError;
use RunOrg\Repository\JournalRepository;

final class LogWorkout
{
    public function __construct(
        private readonly JournalRepository $repository,
        private readonly RenderChart $charts,
    ) {
    }

    public function handle(DateTimeImmutable $date, float $km, string $note = ''): MonthJournal
    {
        $period = YearMonth::fromDate($date);
        if (!$this->repository->monthExists($period)) {
            throw new UserError(sprintf(
                'Нет журнала %s. Сначала: php bin/run init %s --target <km>',
                $period,
                $period
            ));
        }

        $journal = $this->repository->loadMonth($period)
            ->withWorkout(new Workout($date, $km, $note));
        $this->repository->saveMonth($journal);
        $this->charts->renderMonth($period);
        if ($this->repository->yearExists($period->year())) {
            $this->charts->renderYear($period->year());
        }

        return $journal;
    }
}
