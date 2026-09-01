<?php

declare(strict_types=1);

namespace RunOrg\Chart;

use RunOrg\Domain\BurnUpSeries;

interface ChartRenderer
{
    public function render(BurnUpSeries $series, string $outputPath): void;
}
