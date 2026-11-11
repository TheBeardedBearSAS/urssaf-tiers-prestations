<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

/**
 * infoRejet of method 070: regularisation, contestation or cancellation reason (code kept raw, e.g. CONTEST_AUTRE).
 */
final readonly class RejectionInfo
{
    private function __construct(private string $code, private string $comment) {}

    public static function of(string $code, string $comment): self
    {
        return new self($code, $comment);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function comment(): string
    {
        return $this->comment;
    }
}
