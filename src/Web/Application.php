<?php

declare(strict_types=1);

namespace RunOrg\Web;

use DateTimeImmutable;
use RunOrg\Application\LogWorkout;
use RunOrg\Application\RenderChart;
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
        private readonly DateTimeImmutable $today,
    ) {
    }

    public static function fromConfig(Config $config, ?DateTimeImmutable $today = null): self
    {
        $repository = new FileJournalRepository($config->dataDir());
        $renderer = new GnuplotRenderer($config->gnuplot(), $config->gnuplotScript());
        $charts = new RenderChart($repository, $renderer);

        return new self(
            $repository,
            $charts,
            new LogWorkout($repository, $charts),
            $today ?? Date::today(),
        );
    }

    public function handle(Request $request): Response
    {
        try {
            $svg = $request->query('svg');
            if ($svg !== null && $svg !== '') {
                if ($request->method() !== 'GET') {
                    return Pages::message('Ошибка', 'Метод не поддерживается', 405);
                }

                return $this->svg($svg);
            }

            $month = $request->query('month');
            if ($month !== null && $month !== '') {
                if ($request->method() === 'POST') {
                    return $this->logMonth($request, $month);
                }
                if ($request->method() !== 'GET') {
                    return Pages::message('Ошибка', 'Метод не поддерживается', 405);
                }

                return $this->monthPage($month);
            }

            if ($request->method() !== 'GET') {
                return Pages::message('Ошибка', 'Метод не поддерживается', 405);
            }

            return $this->yearPage();
        } catch (UserError $error) {
            return Pages::message('Ошибка', $error->getMessage(), 400);
        } catch (InfrastructureError $error) {
            return Pages::message('Ошибка', $error->getMessage(), 500);
        } catch (Throwable $error) {
            return Pages::message('Ошибка', $error->getMessage(), 500);
        }
    }

    private function yearPage(): Response
    {
        $year = (int) $this->today->format('Y');
        if (!$this->repository->yearExists($year)) {
            return Pages::message('Нет журнала', "Нет журнала за {$year}", 404);
        }

        $journal = $this->charts->aggregateYear($year);
        $this->ensureYearSvg($year);

        $todayPeriod = YearMonth::fromDate($this->today);
        $months = [];
        for ($month = 1; $month <= $todayPeriod->month(); $month++) {
            $period = new YearMonth($year, $month);
            if (!$this->repository->monthExists($period)) {
                continue;
            }
            $months[] = [
                'period' => $period,
                'journal' => $this->repository->loadMonth($period),
            ];
        }

        return Response::html(Pages::year(
            $journal,
            $months,
            $this->svgSrc('year', $this->repository->yearSvgPath($year)),
        ));
    }

    private function monthPage(string $monthText): Response
    {
        $period = $this->accessiblePeriod($monthText);
        if ($period === null) {
            return Pages::message('Нет журнала', 'Журнал месяца не найден', 404);
        }

        return $this->monthPageForPeriod($period);
    }

    /**
     * @param array{date?: string, km?: string, note?: string} $form
     */
    private function monthPageForPeriod(
        YearMonth $period,
        ?string $error = null,
        array $form = [],
    ): Response {
        $this->ensureMonthSvg($period);
        $journal = $this->repository->loadMonth($period);
        $todayPeriod = YearMonth::fromDate($this->today);
        $defaultDate = $period->equals($todayPeriod)
            ? $this->today->format('Y-m-d')
            : $period->lastDay()->format('Y-m-d');

        return Response::html(Pages::month(
            $journal,
            $this->svgSrc((string) $period, $this->repository->monthSvgPath($period)),
            $defaultDate,
            $period->firstDay()->format('Y-m-d'),
            $period->lastDay()->format('Y-m-d'),
            $error,
            $form,
        ));
    }

    private function logMonth(Request $request, string $monthText): Response
    {
        $period = $this->accessiblePeriod($monthText);
        if ($period === null) {
            return Pages::message('Нет журнала', 'Журнал месяца не найден', 404);
        }

        $form = [
            'date' => $request->post('date') ?? '',
            'km' => $request->post('km') ?? '',
            'note' => $request->post('note') ?? '',
        ];

        try {
            $date = Date::parse($form['date']);
            $km = Number::parsePositive($form['km'], 'Дистанция');
            if (!$period->contains($date)) {
                throw new UserError(sprintf(
                    'Дата %s не относится к %s',
                    $date->format('Y-m-d'),
                    $period
                ));
            }
            $this->logWorkout->handle($date, $km, $form['note']);
        } catch (UserError $error) {
            return $this->monthPageForPeriod($period, $error->getMessage(), $form);
        }

        return Response::redirect('/?month=' . $period);
    }

    private function svg(string $target): Response
    {
        if ($target === 'year') {
            $year = (int) $this->today->format('Y');
            if (!$this->repository->yearExists($year)) {
                return Pages::message('Нет журнала', "Нет журнала за {$year}", 404);
            }
            $this->ensureYearSvg($year);

            return $this->fileSvg($this->repository->yearSvgPath($year));
        }

        $period = $this->accessiblePeriod($target);
        if ($period === null) {
            return Pages::message('Нет журнала', 'Журнал месяца не найден', 404);
        }
        $this->ensureMonthSvg($period);

        return $this->fileSvg($this->repository->monthSvgPath($period));
    }

    private function fileSvg(string $path): Response
    {
        if (!is_file($path)) {
            return Pages::message('Нет графика', 'График ещё не построен', 404);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new InfrastructureError("Не удалось прочитать {$path}");
        }

        return Response::svg($contents);
    }

    private function accessiblePeriod(string $monthText): ?YearMonth
    {
        try {
            $period = YearMonth::fromString($monthText);
        } catch (UserError) {
            return null;
        }

        $todayPeriod = YearMonth::fromDate($this->today);
        if ($period->year() !== $todayPeriod->year() || $period->month() > $todayPeriod->month()) {
            return null;
        }
        if (!$this->repository->monthExists($period)) {
            return null;
        }

        return $period;
    }

    private function ensureYearSvg(int $year): void
    {
        if (!is_file($this->repository->yearSvgPath($year))) {
            $this->charts->renderYear($year);
        }
    }

    private function ensureMonthSvg(YearMonth $period): void
    {
        if (!is_file($this->repository->monthSvgPath($period))) {
            $this->charts->renderMonth($period);
        }
    }

    private function svgSrc(string $target, string $path): string
    {
        $mtime = is_file($path) ? (string) filemtime($path) : '0';

        return '/?svg=' . rawurlencode($target) . '&t=' . $mtime;
    }
}
