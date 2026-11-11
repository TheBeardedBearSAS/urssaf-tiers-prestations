<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Validation;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;

/**
 * A single validation finding.
 *
 * - path: English dotted path of the offending field, list items indexed with brackets
 *   (e.g. "address.postalCode", "lines[2].quantity"); empty string targets the validated subject itself.
 * - code: stable snake_case code "<subject>.<reason>" (e.g. "birth_name.invalid_format").
 */
final readonly class Violation
{
    private const PATH_PATTERN = '/^[A-Za-z_]\w*(?:\[\d+\])*(?:\.[A-Za-z_]\w*(?:\[\d+\])*)*$/D';
    private const CODE_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D';

    /**
     * @param array<string, scalar> $params
     */
    private function __construct(
        private string $path,
        private string $code,
        private string $message,
        private Severity $severity,
        private array $params,
    ) {
        self::assertPath($path);
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw InvariantViolation::because(
                'violation.invalid_code',
                'A violation code must be a dotted snake_case identifier such as "subject.reason".',
            );
        }
    }

    /**
     * @param array<string, scalar> $params
     */
    public static function error(string $path, string $code, string $message, array $params = []): self
    {
        return new self($path, $code, $message, Severity::Error, $params);
    }

    /**
     * @param array<string, scalar> $params
     */
    public static function warning(string $path, string $code, string $message, array $params = []): self
    {
        return new self($path, $code, $message, Severity::Warning, $params);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): Severity
    {
        return $this->severity;
    }

    /**
     * @return array<string, scalar>
     */
    public function params(): array
    {
        return $this->params;
    }

    public function isError(): bool
    {
        return $this->severity === Severity::Error;
    }

    /**
     * Nests the violation under a parent path: "quantity" prefixed by "lines[2]" becomes "lines[2].quantity".
     */
    public function withPathPrefix(string $prefix): self
    {
        if ($prefix === '') {
            return $this;
        }
        return new self(
            $this->path === '' ? $prefix : $prefix . '.' . $this->path,
            $this->code,
            $this->message,
            $this->severity,
            $this->params,
        );
    }

    public function equals(self $other): bool
    {
        return $this->path === $other->path
            && $this->code === $other->code
            && $this->message === $other->message
            && $this->severity === $other->severity
            && $this->params === $other->params;
    }

    private static function assertPath(string $path): void
    {
        if ($path !== '' && preg_match(self::PATH_PATTERN, $path) !== 1) {
            throw InvariantViolation::because(
                'violation.invalid_path',
                'A violation path must be a dotted path such as "address.postalCode" or "lines[2].quantity".',
            );
        }
    }
}
