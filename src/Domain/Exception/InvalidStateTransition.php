<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Exception;

/**
 * Thrown when an aggregate is asked to move from a state to another one that is not allowed.
 *
 * Violation code: "<aggregate>.invalid_state_transition" (aggregate in snake_case, e.g. "payment_request").
 */
final class InvalidStateTransition extends DomainException
{
    public static function between(string $aggregate, string $from, string $to): self
    {
        return new self(
            \sprintf('Invalid %s state transition from "%s" to "%s".', $aggregate, $from, $to),
            $aggregate . '.invalid_state_transition',
            ['aggregate' => $aggregate, 'from' => $from, 'to' => $to],
        );
    }

    public function aggregate(): string
    {
        return (string) $this->params()['aggregate'];
    }

    public function from(): string
    {
        return (string) $this->params()['from'];
    }

    public function to(): string
    {
        return (string) $this->params()['to'];
    }
}
