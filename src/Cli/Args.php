<?php

declare(strict_types=1);

namespace RunOrg\Cli;

use RunOrg\Exception\UserError;

final class Args
{
    /** @var list<string> */
    private array $positional = [];

    /** @var array<string, string|true> */
    private array $options = [];

    /**
     * @param list<string> $argv arguments after the command name
     */
    public static function parse(array $argv): self
    {
        $self = new self();
        $count = count($argv);
        for ($i = 0; $i < $count; $i++) {
            $token = $argv[$i];
            if ($token === '--') {
                $self->positional = array_merge($self->positional, array_slice($argv, $i + 1));
                break;
            }
            if (str_starts_with($token, '--') && $token !== '--') {
                $name = substr($token, 2);
                $value = true;
                if (str_contains($name, '=')) {
                    [$name, $value] = explode('=', $name, 2);
                } elseif ($i + 1 < $count && !str_starts_with($argv[$i + 1], '-')) {
                    $value = $argv[++$i];
                }
                $self->options[$name] = $value;
                continue;
            }
            if (str_starts_with($token, '-') && $token !== '-') {
                $self->options[substr($token, 1)] = true;
                continue;
            }
            $self->positional[] = $token;
        }

        return $self;
    }

    public function wantsHelp(): bool
    {
        return isset($this->options['help']) || isset($this->options['h']);
    }

    public function option(string $name): ?string
    {
        if (!isset($this->options[$name]) || $this->options[$name] === true) {
            return null;
        }

        return (string) $this->options[$name];
    }

    public function requireOption(string $name, string $description): string
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            throw new UserError($description);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function positional(): array
    {
        return $this->positional;
    }

    public function positionalAt(int $index): ?string
    {
        return $this->positional[$index] ?? null;
    }
}
