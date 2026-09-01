<?php

declare(strict_types=1);

namespace RunOrg\Application;

use RunOrg\Chart\ChartRenderer;
use RunOrg\Domain\BurnUpSeries;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;
use RunOrg\Markdown\FileJournalRepository;
use RunOrg\Repository\JournalRepository;

final class RenderChart
{
    public function __construct(
        private readonly JournalRepository $repository,
        private readonly ChartRenderer $renderer,
    ) {
    }

    public function renderMonth(YearMonth $period): string
    {
        $journal = $this->repository->loadMonth($period);
        $series = BurnUpSeries::fromMonth($journal);
        $output = $this->svgPathForMonth($period);
        $this->renderer->render($series, $output);

        return $output;
    }

    public function renderYear(int $year): string
    {
        $journal = $this->aggregateYear($year);
        $this->repository->saveYear($journal);
        $series = BurnUpSeries::fromYear(
            $year,
            $journal->targetKm(),
            $journal->recordedDistances()
        );
        $output = $this->svgPathForYear($year);
        $this->renderer->render($series, $output);

        return $output;
    }

    /**
     * @return list<string>
     */
    public function renderAll(): array
    {
        $paths = [];
        foreach ($this->repository->listYears() as $year) {
            foreach ($this->repository->loadMonthsOfYear($year) as $journal) {
                $paths[] = $this->renderMonth($journal->period());
            }
            if ($this->repository->yearExists($year)) {
                $paths[] = $this->renderYear($year);
            }
        }

        return $paths;
    }

    public function aggregateYear(int $year): YearJournal
    {
        $journal = $this->repository->loadYear($year);
        $totals = [];
        for ($month = 1; $month <= 12; $month++) {
            $period = new YearMonth($year, $month);
            if (!$this->repository->monthExists($period)) {
                $totals[$month] = null;
                continue;
            }
            $monthJournal = $this->repository->loadMonth($period);
            $totals[$month] = $monthJournal->workouts() === [] ? null : $monthJournal->totalKm();
        }

        return $journal->withMonthlyTotals($totals);
    }

    private function svgPathForMonth(YearMonth $period): string
    {
        if ($this->repository instanceof FileJournalRepository) {
            return $this->repository->monthSvgPath($period);
        }

        return sprintf('%02d.svg', $period->month());
    }

    private function svgPathForYear(int $year): string
    {
        if ($this->repository instanceof FileJournalRepository) {
            return $this->repository->yearSvgPath($year);
        }

        return 'year.svg';
    }
}
