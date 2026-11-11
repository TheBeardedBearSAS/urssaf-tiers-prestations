<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

final class TranslationsTest extends TestCase
{
    /** Codes that are technical (configuration, wire format) and never shown to an end user. */
    private const NOT_TRANSLATED = [
        'menage.fr',
        'access_token.negative_margin',
        'credentials.client_id_empty',
        'credentials.client_secret_empty',
        'violation.invalid_code',
        'violation.invalid_path',
        'workflow_policy.default_page_size_above_max',
        'workflow_policy.invalid_batch_size',
        'workflow_policy.invalid_limit',
        'workflow_policy.invalid_max_submission_attempts',
        'workflow_policy.invalid_page_size',
    ];

    public function testEveryViolationCodeOfTheDomainHasAnEnglishMessage(): void
    {
        $messages = self::messages();

        $missing = array_values(array_diff(self::codesInSources(), self::NOT_TRANSLATED, array_keys($messages)));

        static::assertSame([], $missing, 'Violation codes without a translation.');
    }

    public function testEveryMessageIsAFilledSentence(): void
    {
        foreach (self::messages() as $code => $message) {
            static::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $code);
            static::assertMatchesRegularExpression('/^[A-Z].+\.$/', $message, $code);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function messages(): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped; every entry is checked below.
        $parsed = Yaml::parseFile(\dirname(__DIR__, 3) . '/src/Bridge/Symfony/Resources/translations/urssaf_tiers_prestations.en.yaml');
        static::assertIsArray($parsed);
        $messages = [];
        foreach ($parsed as $code => $message) { // @mago-expect analysis:mixed-assignment narrowed by the assertion below.
            static::assertIsString($message);
            $messages[(string) $code] = $message;
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private static function codesInSources(): array
    {
        $codes = [];
        foreach (['Domain', 'Application'] as $layer) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 3) . '/src/' . $layer, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                Assert::assertInstanceOf(SplFileInfo::class, $file);
                $matches = [];
                preg_match_all("/'([a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*)+)'/", (string) file_get_contents($file->getPathname()), $matches);
                array_push($codes, ...$matches[1]);
            }
        }

        return array_values(array_unique($codes));
    }
}
