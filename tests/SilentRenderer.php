<?php

declare(strict_types=1);

use RunOrg\Chart\ChartRenderer;
use RunOrg\Domain\BurnUpSeries;

final class SilentRenderer implements ChartRenderer
{
    public function render(BurnUpSeries $series, string $outputPath): void
    {
    }
}
