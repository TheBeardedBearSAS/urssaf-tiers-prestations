<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Immutable, ordered selection of stored aggregates: the small query toolkit of the in-memory repositories.
 *
 * Sorting is stable (usort since PHP 8.0), so items that compare equal keep their previous order.
 *
 * @template T of object
 */
final readonly class Selection
{
    /** @var list<T> */
    private array $items;

    /**
     * @param array<array-key, T> $items
     */
    private function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    /**
     * @template U of object
     *
     * @param array<array-key, U> $items
     *
     * @return self<U>
     */
    public static function of(array $items): self
    {
        return new self($items);
    }

    /**
     * Null sorts before any instant.
     */
    public static function compareInstants(?Instant $a, ?Instant $b): int
    {
        if (!$a instanceof Instant) {
            return $b instanceof Instant ? -1 : 0;
        }

        return $b instanceof Instant ? $a->toDateTimeImmutable() <=> $b->toDateTimeImmutable() : 1;
    }

    /**
     * @param callable(T): bool $predicate
     *
     * @return self<T>
     */
    public function where(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    /**
     * @param callable(T, T): int $comparator
     *
     * @return self<T>
     */
    public function sortedBy(callable $comparator): self
    {
        $items = $this->items;
        usort($items, $comparator);

        return new self($items);
    }

    /**
     * Keeps the first item of each distinct key.
     *
     * @param callable(T): string $keyOf
     *
     * @return self<T>
     */
    public function distinctBy(callable $keyOf): self
    {
        $distinct = [];
        foreach ($this->items as $item) {
            $distinct[$keyOf($item)] ??= $item;
        }

        return new self($distinct);
    }

    /**
     * @param positive-int $limit
     *
     * @return self<T>
     */
    public function limit(int $limit): self
    {
        return new self(\array_slice($this->items, 0, $limit));
    }

    /**
     * Copies of the selected items, so callers never share the stored instances.
     *
     * @return list<T>
     */
    public function copies(): array
    {
        $copies = [];
        foreach ($this->items as $item) {
            $copies[] = clone $item;
        }

        return $copies;
    }

    /**
     * @return list<T>
     */
    public function all(): array
    {
        return $this->items;
    }
}
