#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';
require __DIR__ . '/harness.php';
require __DIR__ . '/SilentRenderer.php';

$passed = 0;
$failures = 0;

require __DIR__ . '/domain.php';
require __DIR__ . '/markdown.php';
require __DIR__ . '/cli.php';

echo "\n{$passed} passed, {$failures} failed\n";
exit($failures === 0 ? 0 : 1);
