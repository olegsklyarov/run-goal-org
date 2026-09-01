<?php

declare(strict_types=1);

namespace RunOrg;

use RunOrg\Exception\InfrastructureError;

final class Config
{
    public function __construct(
        private readonly string $dataDir,
        private readonly string $gnuplot,
        private readonly string $gnuplotScript,
    ) {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new InfrastructureError("Не найден файл конфигурации {$path}");
        }

        /** @var mixed $data */
        $data = require $path;
        if (!is_array($data)) {
            throw new InfrastructureError("Конфигурация {$path} должна возвращать массив");
        }

        $dataDir = self::requiredString($data, 'data_dir', $path);
        $gnuplot = self::requiredString($data, 'gnuplot', $path);
        $script = self::requiredString($data, 'gnuplot_script', $path);

        return new self($dataDir, $gnuplot, $script);
    }

    public function dataDir(): string
    {
        return $this->dataDir;
    }

    public function gnuplot(): string
    {
        return $this->gnuplot;
    }

    public function gnuplotScript(): string
    {
        return $this->gnuplotScript;
    }

    /**
     * @param array<mixed> $data
     */
    private static function requiredString(array $data, string $key, string $path): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
            throw new InfrastructureError("В {$path} задайте непустую строку {$key}");
        }

        return $data[$key];
    }
}
