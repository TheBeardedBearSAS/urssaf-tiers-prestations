<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Recipe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\UrssafTiersPrestationsBundle;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

/**
 * The Symfony Flex recipe stays in line with the bundle, and the endpoint generator publishes it.
 */
final class FlexRecipeTest extends TestCase
{
    private const RECIPE = __DIR__ . '/../../../recipe/thebeardedbear/urssaf-tiers-prestations/0.2';

    public function testTheManifestRegistersTheBundleAndCopiesTheConfiguration(): void
    {
        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertions below narrow it.
        $manifest = json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($manifest);
        static::assertSame([UrssafTiersPrestationsBundle::class => ['all']], $manifest['bundles']);
        static::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        static::assertIsArray($manifest['env']);
        static::assertSame(['URSSAF_ENV' => 'sandbox', 'URSSAF_CLIENT_ID' => '', 'URSSAF_CLIENT_SECRET' => ''], array_diff_key($manifest['env'], ['#1' => true]));
    }

    public function testTheCopiedConfigurationBootsTheBundle(): void
    {
        /** @var array{urssaf_tiers_prestations: array<string, mixed>, 'when@test': array{urssaf_tiers_prestations: array<string, mixed>}} $config */
        $config = self::recipeConfig();
        $main = $config['urssaf_tiers_prestations'];
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        foreach (['URSSAF_ENV' => 'sandbox', 'URSSAF_CLIENT_ID' => 'client', 'URSSAF_CLIENT_SECRET' => 'secret'] as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        try {
            // Merged over the test defaults (HTTP client, in-memory persistence, a test tenant).
            $harness = Harness::boot($main);
            $command = $harness->command('urssaf:check');
            static::assertSame(0, $command->execute(['--skip-remote' => true]));
            static::assertMatchesRegularExpression('/^\| credentials +\| default +\| ok /m', $command->getDisplay());

            static::assertTrue(Harness::boot([...$main, ...$config['when@test']['urssaf_tiers_prestations']])->has(SimulatedUrssafGateway::class));
        } finally {
            unset($_SERVER['URSSAF_ENV'], $_ENV['URSSAF_ENV'], $_SERVER['URSSAF_CLIENT_ID'], $_ENV['URSSAF_CLIENT_ID'], $_SERVER['URSSAF_CLIENT_SECRET'], $_ENV['URSSAF_CLIENT_SECRET']);
        }
    }

    public function testTheEndpointGeneratorPublishesTheRecipe(): void
    {
        $output = \dirname(__DIR__, 3) . '/var/test-flex-endpoint';
        (new Filesystem())->remove($output);
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint', $output, 'https://flex.example.com/urssaf/']);

        static::assertSame(0, $process->run());

        $index = (string) file_get_contents($output . '/index.json');
        static::assertStringContainsString('"aliases": {}', $index);
        static::assertSame([
            'aliases' => [],
            'recipes' => ['thebeardedbear/urssaf-tiers-prestations' => ['0.2']],
            'branch' => 'main',
            'is_contrib' => false,
            '_links' => [
                'repository' => 'https://flex.example.com/urssaf',
                'origin_template' => '{package}:{version}@https://flex.example.com/urssaf',
                'recipe_template' => 'https://flex.example.com/urssaf/{package_dotted}.{version}.json',
            ],
        ], json_decode($index, true, 512, JSON_THROW_ON_ERROR));
        /** @var array{manifests: array<string, array{manifest: mixed, files: array<string, array{contents: list<string>, executable: bool}>, ref: string}>} $recipe */
        $recipe = json_decode((string) file_get_contents($output . '/thebeardedbear.urssaf-tiers-prestations.0.2.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest = $recipe['manifests']['thebeardedbear/urssaf-tiers-prestations'];
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $manifest['ref']);
        static::assertSame(['config/packages/urssaf_tiers_prestations.yaml'], array_keys($manifest['files']));
        $file = $manifest['files']['config/packages/urssaf_tiers_prestations.yaml'];
        static::assertFalse($file['executable']);
        static::assertSame((string) file_get_contents(self::RECIPE . '/config/packages/urssaf_tiers_prestations.yaml'), implode("\n", $file['contents']) . "\n");
        static::assertSame(json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR), $manifest['manifest']);
        (new Filesystem())->remove($output);
    }

    public function testTheGeneratorNeedsItsArguments(): void
    {
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint']);

        static::assertSame(2, $process->run());
        static::assertStringContainsString('Usage: bin/build-flex-endpoint <output-dir> <base-url>', $process->getErrorOutput());
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function recipeConfig(): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped: the assertion below narrows it.
        $config = Yaml::parseFile(self::RECIPE . '/config/packages/urssaf_tiers_prestations.yaml');
        static::assertIsArray($config);

        return $config;
    }
}
