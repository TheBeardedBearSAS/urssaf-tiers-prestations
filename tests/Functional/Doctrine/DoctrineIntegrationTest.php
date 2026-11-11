<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;

final class DoctrineIntegrationTest extends TestCase
{
    public function testItRegistersEveryTypeOnceAndIsIdempotent(): void
    {
        DoctrineIntegration::registerTypes();
        DoctrineIntegration::registerTypes();

        static::assertCount(36, DoctrineIntegration::types());
        foreach (DoctrineIntegration::types() as $name => $class) {
            static::assertStringStartsWith(DoctrineIntegration::TABLE_PREFIX, $name);
            static::assertSame(Type::getType($name)::class, $class);
            static::assertSame($name, Type::lookupName(Type::getType($name)));
        }
    }

    #[RunInSeparateProcess]
    public function testItRegistersTheMissingTypesWhenSomeAreAlreadyRegistered(): void
    {
        $names = array_keys(DoctrineIntegration::types());
        $first = $names[0];
        static::assertFalse(Type::hasType($first));
        Type::addType($first, DoctrineIntegration::types()[$first]);

        DoctrineIntegration::registerTypes();

        foreach ($names as $name) {
            static::assertTrue(Type::hasType($name));
        }
    }

    public function testTheMappingDirectoryHoldsOneFilePerMappedClass(): void
    {
        $directory = DoctrineIntegration::mappingDirectory();
        $files = glob($directory . '/*.orm.xml');

        static::assertDirectoryExists($directory);
        static::assertIsArray($files);
        static::assertCount(15, $files);
        static::assertSame(
            [
                'Bridge\\Doctrine\\Entity\\PaymentRequestProgress',
                'Domain\\Customer\\Address\\PostalAddress',
                'Domain\\Customer\\Address\\StreetAddress',
                'Domain\\Customer\\Bank\\BankAccount',
                'Domain\\Customer\\ContactDetails',
                'Domain\\Customer\\Customer',
                'Domain\\Customer\\CustomerRegistration',
                'Domain\\Customer\\Identity\\BirthPlace',
                'Domain\\Customer\\Identity\\CivilStatus',
                'Domain\\Customer\\RegistrationStatus',
                'Domain\\PaymentRequest\\Invoice',
                'Domain\\PaymentRequest\\InvoicedCustomer',
                'Domain\\PaymentRequest\\PaymentRequest',
                'Domain\\PaymentRequest\\Timeline',
                'Domain\\Shared\\EmploymentPeriod',
            ],
            self::mappedClasses(),
        );
    }

    public function testTheMetadataDriverMapsTheAggregatesAndIgnoresOtherClasses(): void
    {
        $driver = DoctrineIntegration::mappingDriver();

        static::assertFalse($driver->isTransient(CustomerRegistration::class));
        static::assertFalse($driver->isTransient(PaymentRequest::class));
        static::assertTrue($driver->isTransient(DoctrineIntegration::class));
    }

    public function testItProvidesTheDoctrineBundleConfiguration(): void
    {
        static::assertSame(
            [
                'dbal' => ['types' => DoctrineIntegration::types()],
                'orm' => [
                    'mappings' => [
                        'UrssafTiersPrestations' => [
                            'type' => 'xml',
                            'is_bundle' => false,
                            'dir' => DoctrineIntegration::mappingDirectory(),
                            'prefix' => 'TheBeardedBear\UrssafTiersPrestations',
                            'alias' => 'UrssafTiersPrestations',
                        ],
                    ],
                ],
            ],
            DoctrineIntegration::doctrineBundleConfiguration(),
        );
    }

    /**
     * @return list<string> mapped classes without the common namespace prefix
     */
    private static function mappedClasses(): array
    {
        $classes = DoctrineIntegration::mappingDriver()->getAllClassNames();
        $prefix = DoctrineIntegration::MAPPING_NAMESPACE . '\\';
        $names = array_map(static fn(string $class): string => substr($class, \strlen($prefix)), $classes);
        sort($names);

        return $names;
    }
}
