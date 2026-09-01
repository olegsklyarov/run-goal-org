<?php

declare(strict_types=1);

function test(string $name, callable $fn): void
{
    global $failures, $passed;
    try {
        $fn();
        echo "ok   {$name}\n";
        $passed++;
    } catch (Throwable $error) {
        echo "FAIL {$name}: {$error->getMessage()}\n";
        $failures++;
    }
}

function assertTrue(bool $condition, string $message = 'assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $prefix = $message !== '' ? $message . ': ' : '';
        throw new RuntimeException(
            $prefix . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function assertFloat(float $expected, float $actual, string $message = ''): void
{
    if (abs($expected - $actual) > 0.001) {
        $prefix = $message !== '' ? $message . ': ' : '';
        throw new RuntimeException($prefix . "expected {$expected}, got {$actual}");
    }
}

function expectUserError(callable $fn): void
{
    try {
        $fn();
    } catch (RunOrg\Exception\UserError) {
        return;
    }
    throw new RuntimeException('expected UserError');
}

function tempDir(): string
{
    $path = sys_get_temp_dir() . '/run-org-' . bin2hex(random_bytes(8));
    mkdir($path, 0755, true);

    return $path;
}

function removeDir(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $full = $path . '/' . $name;
        if (is_dir($full)) {
            removeDir($full);
        } else {
            unlink($full);
        }
    }
    rmdir($path);
}
