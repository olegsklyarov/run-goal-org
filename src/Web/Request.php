<?php

declare(strict_types=1);

namespace RunOrg\Web;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     */
    public function __construct(
        private readonly string $method,
        private readonly array $query = [],
        private readonly array $post = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            self::stringMap($_GET),
            self::stringMap($_POST),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function query(string $key): ?string
    {
        return $this->query[$key] ?? null;
    }

    public function post(string $key): ?string
    {
        return $this->post[$key] ?? null;
    }

    /**
     * @param array<mixed> $input
     * @return array<string, string>
     */
    private static function stringMap(array $input): array
    {
        $map = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $map[$key] = $value;
            }
        }

        return $map;
    }
}
