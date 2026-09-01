<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use DateTimeImmutable;
use RunOrg\Exception\UserError;

final class YearMonth
{
    public function __construct(
        private readonly int $year,
        private readonly int $month,
    ) {
        if ($year < 1 || $year > 9999) {
            throw new UserError("Год должен иметь формат YYYY: {$year}");
        }
        if ($month < 1 || $month > 12) {
            throw new UserError("Некорректный номер месяца: {$month}");
        }
    }

    public static function fromString(string $value): self
    {
        $text = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $text, $matches)) {
            throw new UserError("Месяц должен иметь формат YYYY-MM: {$text}");
        }

        return new self((int) $matches[1], (int) $matches[2]);
    }

    public static function fromDate(DateTimeImmutable $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'));
    }

    public static function fromYear(string $value): int
    {
        $text = trim($value);
        if (!preg_match('/^\d{4}$/', $text)) {
            throw new UserError("Год должен иметь формат YYYY: {$text}");
        }

        return (int) $text;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): int
    {
        return $this->month;
    }

    public function daysInMonth(): int
    {
        return (int) $this->firstDay()->format('t');
    }

    public function firstDay(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month));
    }

    public function lastDay(): DateTimeImmutable
    {
        return $this->dateForDay($this->daysInMonth());
    }

    public function dateForDay(int $day): DateTimeImmutable
    {
        if ($day < 1 || $day > $this->daysInMonth()) {
            throw new UserError(sprintf(
                'Некорректная дата: %04d-%02d-%02d',
                $this->year,
                $this->month,
                $day
            ));
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $this->year, $this->month, $day));
    }

    public function contains(DateTimeImmutable $date): bool
    {
        return (int) $date->format('Y') === $this->year
            && (int) $date->format('n') === $this->month;
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->month === $other->month;
    }

    public function __toString(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
