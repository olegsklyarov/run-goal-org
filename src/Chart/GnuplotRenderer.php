<?php

declare(strict_types=1);

namespace RunOrg\Chart;

use RunOrg\Domain\BurnUpSeries;
use RunOrg\Exception\InfrastructureError;

final class GnuplotRenderer implements ChartRenderer
{
    public function __construct(
        private readonly string $gnuplot,
        private readonly string $scriptPath,
        private readonly SvgPostprocessor $postprocessor = new SvgPostprocessor(),
    ) {
    }

    public function render(BurnUpSeries $series, string $outputPath): void
    {
        $executable = $this->findGnuplot();
        if (!is_readable($this->scriptPath)) {
            throw new InfrastructureError("Не найден файл {$this->scriptPath}");
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new InfrastructureError("Не удалось создать каталог {$directory}");
        }

        $dataPath = $outputPath . '.plot-' . bin2hex(random_bytes(4)) . '.tsv';
        $temporaryOutput = $outputPath . '.tmp.' . bin2hex(random_bytes(4));

        try {
            $this->writeTsv($dataPath, $series);
            $this->callGnuplot(
                $executable,
                $dataPath,
                $temporaryOutput,
                $series
            );
            $this->postprocessor->setA4Landscape($temporaryOutput);
            chmod($temporaryOutput, 0644);
            if (!rename($temporaryOutput, $outputPath)) {
                throw new InfrastructureError("Не удалось сохранить {$outputPath}");
            }
        } finally {
            foreach ([$dataPath, $temporaryOutput] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    private function writeTsv(string $path, BurnUpSeries $series): void
    {
        $lines = [];
        foreach ($series->points() as $point) {
            $actual = $point->actual();
            $lines[] = sprintf(
                "%d\t%s\t%d\t%s",
                $point->index(),
                $point->label(),
                $point->isSunday() ? 1 : 0,
                $actual === null ? 'NaN' : sprintf('%.2f', $actual)
            );
        }

        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            throw new InfrastructureError("Не удалось записать {$path}");
        }
    }

    private function callGnuplot(
        string $executable,
        string $dataPath,
        string $outputPath,
        BurnUpSeries $series
    ): void {
        $command = [
            $executable,
            '-c',
            $this->scriptPath,
            $dataPath,
            $outputPath,
            $series->title(),
            (string) ($series->targetKm() ?? 0),
            (string) $series->yMax(),
            (string) $series->pointCount(),
            $series->xLabel(),
            (string) $series->yStep(),
        ];

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new InfrastructureError('Не удалось запустить gnuplot');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) {
            $message = trim((string) $stderr . (string) $stdout);
            throw new InfrastructureError(
                'gnuplot завершился с ошибкой' . ($message !== '' ? ": {$message}" : '')
            );
        }
    }

    private function findGnuplot(): string
    {
        if ($this->gnuplot !== 'gnuplot' && is_executable($this->gnuplot)) {
            return $this->gnuplot;
        }

        $paths = getenv('PATH');
        if (is_string($paths)) {
            foreach (explode(PATH_SEPARATOR, $paths) as $directory) {
                $candidate = $directory . DIRECTORY_SEPARATOR . 'gnuplot';
                if (is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new InfrastructureError('Не найден gnuplot в PATH');
    }
}
