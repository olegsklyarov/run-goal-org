<?php

declare(strict_types=1);

namespace RunOrg\Web;

use RunOrg\Domain\Labels;
use RunOrg\Domain\MonthJournal;
use RunOrg\Domain\Number;
use RunOrg\Domain\YearJournal;
use RunOrg\Domain\YearMonth;

final class Pages
{
    /**
     * @param list<array{period: YearMonth, journal: MonthJournal}> $months
     */
    public static function year(YearJournal $journal, array $months, string $svgUrl): string
    {
        $year = $journal->year();
        $title = "Бег: {$year}";
        $target = Number::formatKm($journal->targetKm());
        $total = Number::formatKm($journal->totalKm());
        $remaining = Number::formatKm($journal->targetKm() - $journal->totalKm());

        $items = '';
        foreach ($months as $item) {
            $period = $item['period'];
            $monthJournal = $item['journal'];
            $name = Labels::monthName($period->month());
            $km = $monthJournal->workouts() === []
                ? '—'
                : Number::formatKm($monthJournal->totalKm()) . ' км';
            $href = self::e('/?month=' . $period);
            $nameEsc = self::e($name);
            $kmEsc = self::e($km);
            $items .= "<li><a href=\"{$href}\"><span>{$nameEsc}</span><span>{$kmEsc}</span></a></li>\n";
        }

        $body = '<h1>' . self::e($title) . '</h1>'
            . self::stats([
                "Цель: {$target} км",
                "Факт: {$total} км",
                "Осталось: {$remaining} км",
            ])
            . '<p class="chart"><img src="' . self::e($svgUrl) . '" alt="График"></p>'
            . '<h2>Месяцы</h2>'
            . '<ul class="months">' . $items . '</ul>';

        return self::document($title, $body);
    }

    /**
     * @param array{date?: string, km?: string, note?: string} $form
     */
    public static function month(
        MonthJournal $journal,
        string $svgUrl,
        string $defaultDate,
        string $minDate,
        string $maxDate,
        ?string $error = null,
        array $form = [],
    ): string {
        $period = $journal->period();
        $title = sprintf(
            'Бег: %s %d',
            Labels::monthName($period->month()),
            $period->year()
        );
        $date = $form['date'] ?? $defaultDate;
        $km = $form['km'] ?? '';
        $note = $form['note'] ?? '';
        $action = '/?month=' . $period;

        $stats = [];
        $target = $journal->targetKm();
        if ($target === null) {
            $stats[] = 'Цель: не задана';
        } else {
            $stats[] = 'Цель: ' . Number::formatKm($target) . ' км';
        }
        $stats[] = 'Факт: ' . Number::formatKm($journal->totalKm()) . ' км';
        if ($target !== null) {
            $stats[] = 'Осталось: ' . Number::formatKm($target - $journal->totalKm()) . ' км';
        }

        $errorHtml = $error === null ? '' : '<p class="error">' . self::e($error) . '</p>';

        $rows = '';
        foreach ($journal->workouts() as $workout) {
            $rows .= '<tr>'
                . '<td>' . self::e($workout->date()->format('Y-m-d')) . '</td>'
                . '<td>' . self::e(Number::formatKm($workout->km())) . '</td>'
                . '<td>' . self::e($workout->note()) . '</td>'
                . '</tr>';
        }
        $table = $rows === ''
            ? '<p>Пока нет тренировок.</p>'
            : '<table><thead><tr><th>Дата</th><th>км</th><th>Заметка</th></tr></thead><tbody>'
                . $rows
                . '</tbody></table>';

        $yearHref = self::e('/');
        $yearLabel = self::e((string) $period->year());
        $body = '<p class="back"><a href="' . $yearHref . '">← ' . $yearLabel . '</a></p>'
            . '<h1>' . self::e($title) . '</h1>'
            . self::stats($stats)
            . '<p class="chart"><img src="' . self::e($svgUrl) . '" alt="График"></p>'
            . $errorHtml
            . '<h2>Добавить тренировку</h2>'
            . '<form method="post" action="' . self::e($action) . '">'
            . '<label>Дата <input type="date" name="date" required'
            . ' value="' . self::e($date) . '"'
            . ' min="' . self::e($minDate) . '"'
            . ' max="' . self::e($maxDate) . '"></label>'
            . '<label>км <input type="text" name="km" required inputmode="decimal"'
            . ' value="' . self::e($km) . '"></label>'
            . '<label>Заметка <input type="text" name="note" value="' . self::e($note) . '"></label>'
            . '<button type="submit">Сохранить</button>'
            . '</form>'
            . '<h2>Тренировки</h2>'
            . $table;

        return self::document($title, $body);
    }

    public static function message(string $title, string $message, int $status): Response
    {
        $body = '<h1>' . self::e($title) . '</h1><p>' . self::e($message) . '</p>'
            . '<p class="back"><a href="/">← К журналу</a></p>';

        return Response::html(self::document($title, $body), $status);
    }

    /**
     * @param list<string> $lines
     */
    private static function stats(array $lines): string
    {
        $html = '<p class="stats">';
        foreach ($lines as $line) {
            $html .= '<span>' . self::e($line) . '</span>';
        }

        return $html . '</p>';
    }

    private static function document(string $title, string $body): string
    {
        $titleEsc = self::e($title);

        return <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titleEsc}</title>
<style>
body { font-family: system-ui, sans-serif; max-width: 52rem; margin: 1.5rem auto; padding: 0 1rem; color: #1a1a1a; }
h1 { font-size: 1.5rem; margin: 0 0 0.75rem; }
h2 { font-size: 1.1rem; margin: 1.5rem 0 0.5rem; }
.stats { display: flex; flex-wrap: wrap; gap: 0.75rem 1.5rem; }
.chart img { max-width: 100%; height: auto; background: #fff; }
.months { list-style: none; padding: 0; margin: 0; }
.months a { display: flex; justify-content: space-between; gap: 1rem; padding: 0.45rem 0; border-bottom: 1px solid #ddd; text-decoration: none; color: inherit; }
.months a:hover { color: #0b57d0; }
form { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: end; }
label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.9rem; }
input { font: inherit; padding: 0.3rem 0.4rem; }
button { font: inherit; padding: 0.4rem 0.8rem; cursor: pointer; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 0.35rem 0.5rem; border-bottom: 1px solid #ddd; }
.error { color: #a40000; }
.back { margin: 0 0 0.75rem; }
a { color: #0b57d0; }
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
