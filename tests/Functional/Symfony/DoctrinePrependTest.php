<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\ExtensionConfigSnapshot;

/**
 * The "doctrine" configuration prepended by the bundle.
 */
final class DoctrinePrependTest extends TestCase
{
    public function testTheNativeLazyObjectsArePrependedOnlyWhenTheRuntimeSupportsThem(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');

        (new TestKernel(['persistence' => ['driver' => 'doctrine']], true))->definitionsRecordedBy($snapshot);

        $orm = self::prependedOrm($snapshot);
        static::assertSame(DoctrineIntegration::nativeLazyObjectsAvailable(), \array_key_exists('enable_native_lazy_objects', $orm));
        static::assertArrayHasKey('mappings', $orm);
        static::assertArrayNotHasKey('entity_managers', $orm);
    }

    public function testTheNativeLazyObjectsStayGlobalWhenAnotherEntityManagerIsSelected(): void
    {
        $snapshot = new ExtensionConfigSnapshot('doctrine');
        $doctrine = ['dbal' => ['driver' => 'pdo_sqlite', 'memory' => true], 'orm' => ['entity_managers' => ['urssaf' => ['connection' => 'default']]]];

        (new TestKernel(['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], $doctrine))->definitionsRecordedBy($snapshot);

        $orm = self::prependedOrm($snapshot);
        static::assertSame(DoctrineIntegration::nativeLazyObjectsAvailable(), \array_key_exists('enable_native_lazy_objects', $orm));
        static::assertArrayNotHasKey('mappings', $orm);
        // @mago-expect analysis:mixed-assignment The recorded configuration is untyped; the assertion below narrows it.
        $managers = $orm['entity_managers'] ?? null;
        static::assertIsArray($managers);
        static::assertArrayHasKey('urssaf', $managers);
        // @mago-expect analysis:mixed-assignment The recorded configuration is untyped; the assertion below narrows it.
        $urssaf = $managers['urssaf'];
        static::assertIsArray($urssaf);
        static::assertArrayHasKey('mappings', $urssaf);
    }

    /**
     * @return array<array-key, mixed> the "orm" section prepended by the bundle (the first configuration recorded)
     */
    private static function prependedOrm(ExtensionConfigSnapshot $snapshot): array
    {
        // @mago-expect analysis:mixed-assignment The recorded configuration is untyped; the assertion below narrows it.
        $orm = $snapshot->configs()[0]['orm'] ?? null;
        static::assertIsArray($orm);

        return $orm;
    }
}
