<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

/**
 * Reads the tables printed by the console commands.
 */
final class Output
{
    /**
     * The rows of the tables of a command output (header rows included), cells trimmed.
     *
     * @return list<list<string>>
     */
    public static function rows(string $display): array
    {
        $rows = [];
        foreach (explode("\n", $display) as $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }
            $rows[] = array_map(trim(...), explode('|', trim($line, '|')));
        }

        return $rows;
    }
}
