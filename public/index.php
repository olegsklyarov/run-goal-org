<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use RunOrg\Config;
use RunOrg\Web\Application;
use RunOrg\Web\Request;

$config = Config::load(dirname(__DIR__) . '/config.php');
$app = Application::fromConfig($config);
$app->handle(Request::fromGlobals())->send();
