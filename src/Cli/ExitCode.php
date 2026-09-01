<?php

declare(strict_types=1);

namespace RunOrg\Cli;

final class ExitCode
{
    public const SUCCESS = 0;
    public const USER_ERROR = 1;
    public const INFRASTRUCTURE_ERROR = 2;
}
