<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Type;

use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MoneyType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TimeZoneType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Conversion scenarios of the scalar column types: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class ScalarTypesTest extends TestCase
{
    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    public function testAnInstantIsStoredInUtcWithoutFractionAndReadBackAsTheSameInstant(): void
    {
        $type = new InstantType();
        $instant = Instant::fromIso8601('2022-02-01T16:40:00+01:00');

        static::assertSame('2022-02-01 15:40:00', $type->convertToDatabaseValue($instant, $this->platform));
        $read = $type->convertToPHPValue('2022-02-01 15:40:00', $this->platform);
        static::assertEquals($instant, $read);
        static::assertInstanceOf(Instant::class, $read);
        static::assertSame('UTC', $read->toDateTimeImmutable()->getTimezone()->getName());
        static::assertSame('DATETIME', $type->getSQLDeclaration([], $this->platform));
    }

    public function testAnInstantIsReadInUtcWhateverTheDefaultTimeZone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');
        try {
            $read = (new InstantType())->convertToPHPValue('2022-02-01 15:40:00', $this->platform);
        } finally {
            date_default_timezone_set($previous);
        }

        static::assertInstanceOf(Instant::class, $read);
        static::assertSame('2022-02-01T15:40:00Z', $read->toIso8601());
    }

    public function testDateTimeTypesRefuseWhatTheyDoNotSupport(): void
    {
        $type = Type::getType(InstantType::NAME);
        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
        $this->assertWrongPhpType($type, '2022-02-01', Instant::class);
        $this->assertWrongStoredType($type, 5, 'string');
        $this->assertInvalidStoredFormat($type, 'yesterday');
    }

    public function testMoneyIsStoredAsCents(): void
    {
        $type = Type::getType(MoneyType::NAME);

        static::assertSame(1102, $type->convertToDatabaseValue(Money::fromCents(1102), $this->platform));
        static::assertEquals(Money::fromCents(1102), $type->convertToPHPValue(1102, $this->platform));
        static::assertEquals(Money::fromCents(1102), $type->convertToPHPValue('1102', $this->platform));
        static::assertEquals(Money::fromCents(0), $type->convertToPHPValue(0, $this->platform));
        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
        static::assertSame('BIGINT', $type->getSQLDeclaration([], $this->platform));
    }

    public function testMoneyRefusesWhatIsNotAnAmountInCents(): void
    {
        $type = Type::getType(MoneyType::NAME);

        $this->assertWrongPhpType($type, 12, Money::class);
        foreach (['12.5', '-3', 1.5, "12\n"] as $stored) {
            $this->assertWrongStoredType($type, $stored, 'int, numeric-string');
        }
        $this->assertInvalidStoredFormat($type, -3);
    }

    public function testATimeZoneIsStoredByIdentifier(): void
    {
        $type = Type::getType(TimeZoneType::NAME);

        static::assertSame('Europe/Paris', $type->convertToDatabaseValue(new DateTimeZone('Europe/Paris'), $this->platform));
        static::assertEquals(new DateTimeZone('Europe/Paris'), $type->convertToPHPValue('Europe/Paris', $this->platform));
        static::assertEquals(new DateTimeZone('+01:00'), $type->convertToPHPValue('+01:00', $this->platform));
        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
        static::assertSame('VARCHAR(64)', $type->getSQLDeclaration(['length' => 64], $this->platform));
    }

    public function testATimeZoneRefusesWhatIsNotAZone(): void
    {
        $type = Type::getType(TimeZoneType::NAME);

        $this->assertWrongPhpType($type, 'Europe/Paris', DateTimeZone::class);
        $this->assertWrongStoredType($type, 3, 'string');
        $this->assertInvalidStoredFormat($type, 'Mars/Olympus');
    }

    /**
     * @param class-string $expected
     */
    private function assertWrongPhpType(Type $type, mixed $value, string $expected): void
    {
        try {
            $type->convertToDatabaseValue($value, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    private function assertWrongStoredType(Type $type, mixed $stored, string $expected): void
    {
        try {
            $type->convertToPHPValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    private function assertInvalidStoredFormat(Type $type, mixed $stored): void
    {
        try {
            $type->convertToPHPValue($stored, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString('Expected format', $exception->getMessage());
        }
    }
}
