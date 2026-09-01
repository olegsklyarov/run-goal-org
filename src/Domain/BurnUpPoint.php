<?php

declare(strict_types=1);

namespace RunOrg\Domain;

final class BurnUpPoint
{
    public function __construct(
        private readonly int $index,
        private readonly string $label,
        private readonly ?float $actual,
        private readonly bool $isSunday,
        private readonly bool $hasMarker,
    ) {
    }

    public function index(): int
    {
        return $this->index;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function actual(): ?float
    {
        return $this->actual;
    }

    public function isSunday(): bool
    {
        return $this->isSunday;
    }

    public function hasMarker(): bool
    {
        return $this->hasMarker;
    }
}
