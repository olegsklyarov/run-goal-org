<?php

declare(strict_types=1);

namespace RunOrg\Domain;

final class Labels
{
    /** @var list<string> Sunday-first abbreviated weekday names. */
    public const WEEKDAYS = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];

    /** @var list<string> */
    public const MONTH_NAMES = [
        'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
        'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь',
    ];

    /** @var list<string> */
    public const MONTH_ABBREVIATIONS = [
        'янв', 'фев', 'мар', 'апр', 'май', 'июн',
        'июл', 'авг', 'сен', 'окт', 'ноя', 'дек',
    ];

    public static function weekday(int $sundayBasedIndex): string
    {
        return self::WEEKDAYS[$sundayBasedIndex];
    }

    public static function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month - 1];
    }

    public static function monthAbbreviation(int $month): string
    {
        return self::MONTH_ABBREVIATIONS[$month - 1];
    }
}
