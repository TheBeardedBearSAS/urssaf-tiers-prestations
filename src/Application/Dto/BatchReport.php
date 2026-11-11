<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * Immutable report of a batch use case, items in processing order.
 */
final readonly class BatchReport
{
    /**
     * @param list<BatchItem> $items
     */
    private function __construct(private array $items) {}

    public static function of(BatchItem ...$items): self
    {
        return new self(array_values($items));
    }

    public function merge(self $other): self
    {
        return new self([...$this->items, ...$other->items]);
    }

    /**
     * @return list<BatchItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return \count($this->items);
    }

    public function count(ItemOutcome $outcome): int
    {
        return \count(array_filter($this->items, static fn(BatchItem $item): bool => $item->outcome === $outcome));
    }
}
