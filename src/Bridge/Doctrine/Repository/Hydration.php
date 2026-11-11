<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository;

/**
 * Typed access to the rows of a DQL query (Doctrine returns untyped results): only instances of the expected
 * aggregate class are returned.
 */
final class Hydration
{
    /**
     * @template T of object
     *
     * @param iterable<mixed> $rows
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function all(iterable $rows, string $class): array
    {
        $entities = [];
        // @mago-expect analysis:mixed-assignment Doctrine rows are untyped; each one is narrowed by the instanceof below.
        foreach ($rows as $row) {
            if (!$row instanceof $class) {
                continue;
            }
            $entities[] = $row;
        }

        return $entities;
    }

    /**
     * @template T of object
     *
     * @param iterable<mixed> $rows
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function first(iterable $rows, string $class): ?object
    {
        return self::all($rows, $class)[0] ?? null;
    }
}
