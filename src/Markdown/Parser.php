<?php

declare(strict_types=1);

namespace RunOrg\Markdown;

use RunOrg\Exception\UserError;

final class Parser
{
    /**
     * @return array<string, string>
     */
    public function parseFrontmatter(string $contents): array
    {
        if (!preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n/s', $contents, $matches)) {
            throw new UserError('Файл должен начинаться с YAML frontmatter между ---');
        }

        $fields = [];
        foreach (preg_split('/\R/', $matches[1]) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*(.*)$/', $line, $pair)) {
                throw new UserError("Некорректная строка frontmatter: {$line}");
            }
            $fields[$pair[1]] = trim($pair[2]);
        }

        return $fields;
    }

    /**
     * @param list<string> $requiredColumns
     * @return list<array<string, string>>
     */
    public function parseTable(string $contents, array $requiredColumns): array
    {
        $lines = preg_split('/\R/', $contents);
        $start = null;
        foreach ($lines as $index => $line) {
            if (str_starts_with(ltrim($line), '|')) {
                $start = $index;
                break;
            }
        }

        if ($start === null) {
            throw new UserError('В файле нет Markdown-таблицы');
        }

        $header = $this->splitRow($lines[$start]);
        $separatorIndex = $start + 1;
        if (!isset($lines[$separatorIndex]) || !$this->isSeparator($lines[$separatorIndex])) {
            throw new UserError('После заголовка таблицы ожидается строка-разделитель');
        }

        $indexByName = [];
        foreach ($header as $index => $name) {
            $indexByName[$name] = $index;
        }
        foreach ($requiredColumns as $column) {
            if (!isset($indexByName[$column])) {
                throw new UserError("В таблице нет колонки {$column}");
            }
        }

        $rows = [];
        for ($i = $separatorIndex + 1; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (!str_starts_with(ltrim($line), '|')) {
                break;
            }
            if ($this->isSeparator($line)) {
                continue;
            }
            $cells = $this->splitRow($line);
            $row = [];
            foreach ($header as $index => $name) {
                $row[$name] = $cells[$index] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function splitRow(string $line): array
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

    private function isSeparator(string $line): bool
    {
        $cells = $this->splitRow($line);
        if ($cells === []) {
            return false;
        }
        foreach ($cells as $cell) {
            if (!preg_match('/^:?-{3,}:?$/', $cell)) {
                return false;
            }
        }

        return true;
    }
}
