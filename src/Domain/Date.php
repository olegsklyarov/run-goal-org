<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use DateTimeImmutable;
use RunOrg\Exception\UserError;

final class Date
{
    public static function parse(string $value): DateTimeImmutable
    {
        $text = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $matches)) {
            throw new UserError("Дата должна иметь формат YYYY-MM-DD: {$text}");
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if (!checkdate($month, $day, $year)) {
            throw new UserError("Некорректная дата: {$text}");
        }

        return new DateTimeImmutable($text);
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today');
    }
}
