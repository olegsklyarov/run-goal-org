<?php

declare(strict_types=1);

namespace RunOrg\Markdown;

final class Writer
{
    /**
     * @param array<string, string> $fields
     */
    public function frontmatter(array $fields): string
    {
        $lines = ['---'];
        foreach ($fields as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        $lines[] = '---';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $header
     * @param list<list<string>> $rows
     */
    public function table(array $header, array $rows): string
    {
        $all = [$header, ...$rows];
        $columns = count($header);
        $widths = array_fill(0, $columns, 3);
        foreach ($all as $row) {
            for ($i = 0; $i < $columns; $i++) {
                $widths[$i] = max($widths[$i], $this->width($row[$i] ?? ''));
            }
        }

        $lines = [];
        $lines[] = $this->formatRow($header, $widths);
        $separator = [];
        foreach ($widths as $width) {
            $separator[] = str_repeat('-', $width);
        }
        $lines[] = '| ' . implode(' | ', $separator) . ' |';
        foreach ($rows as $row) {
            $padded = [];
            for ($i = 0; $i < $columns; $i++) {
                $padded[] = $row[$i] ?? '';
            }
            $lines[] = $this->formatRow($padded, $widths);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $cells
     * @param list<int> $widths
     */
    private function formatRow(array $cells, array $widths): string
    {
        $padded = [];
        foreach ($cells as $index => $cell) {
            $padded[] = $this->pad($cell, $widths[$index]);
        }

        return '| ' . implode(' | ', $padded) . ' |';
    }

    private function pad(string $text, int $width): string
    {
        $length = $this->width($text);
        if ($length >= $width) {
            return $text;
        }

        return $text . str_repeat(' ', $width - $length);
    }

    private function width(string $text): int
    {
        return mb_strlen($text, 'UTF-8');
    }
}
