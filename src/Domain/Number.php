<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use RunOrg\Exception\UserError;

final class Number
{
    public static function parseNonNegative(string $value, string $description): float
    {
        $text = trim($value);
        if (!preg_match('/^\d+(?:\.\d+)?$/', $text)) {
            throw new UserError("{$description} должно быть неотрицательным числом: {$text}");
        }

        return (float) $text;
    }

    public static function parsePositive(string $value, string $description): float
    {
        $number = self::parseNonNegative($value, $description);
        if ($number <= 0.0) {
            throw new UserError("{$description} должна быть больше нуля");
        }

        return $number;
    }

    public static function formatKm(float $km): string
    {
        $text = sprintf('%.2f', $km);
        $text = rtrim($text, '0');
        $text = rtrim($text, '.');

        return $text === '' ? '0' : $text;
    }
}
