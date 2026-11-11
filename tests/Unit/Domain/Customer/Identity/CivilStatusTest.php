<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Identity;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\Civility;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\CivilStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

final class CivilStatusTest extends TestCase
{
    private static function civilStatus(?PersonName $usageName): CivilStatus
    {
        return new CivilStatus(
            Civility::Female,
            PersonName::birthName('Durand'),
            $usageName,
            PersonName::firstNames('Jeanne Marie'),
            CalendarDate::fromString('1980-03-29'),
            BirthPlace::abroad(CountryCode::fromString('99134')),
        );
    }

    public function testAccessors(): void
    {
        $status = self::civilStatus(PersonName::usageName('Martin'));

        static::assertSame(Civility::Female, $status->civility());
        static::assertSame('Durand', $status->birthName()->value());
        static::assertSame('Martin', $status->usageName()?->value());
        static::assertSame('Jeanne Marie', $status->firstNames()->value());
        static::assertSame('1980-03-29', $status->birthDate()->toString());
        static::assertSame('99134', $status->birthPlace()->country()->value());
    }

    public function testUsageNameIsOptional(): void
    {
        static::assertNull(self::civilStatus(null)->usageName());
    }

    public function testUsageNameEqualToBirthNameIsDropped(): void
    {
        static::assertNull(self::civilStatus(PersonName::usageName('Durand'))->usageName());
        static::assertSame('DURAND', self::civilStatus(PersonName::usageName('DURAND'))->usageName()?->value());
    }
}
