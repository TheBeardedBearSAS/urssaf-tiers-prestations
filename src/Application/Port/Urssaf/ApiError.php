<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

/**
 * Error item returned by URSSAF: {"code": "...", "message": "...", "description": "..."}. Kept verbatim (French).
 */
final readonly class ApiError
{
    private function __construct(private string $code, private string $message, private string $description) {}

    public static function of(string $code, string $message, string $description): self
    {
        return new self($code, $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code
            && $this->message === $other->message
            && $this->description === $other->description;
    }
}
