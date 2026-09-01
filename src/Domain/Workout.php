<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use DateTimeImmutable;
use RunOrg\Exception\UserError;

final class Workout
{
    public function __construct(
        private readonly DateTimeImmutable $date,
        private readonly float $km,
        private readonly string $note = '',
    ) {
        if ($km <= 0.0) {
            throw new UserError('Дистанция должна быть больше нуля');
        }
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }

    public function km(): float
    {
        return $this->km;
    }

    public function note(): string
    {
        return $this->note;
    }
}
