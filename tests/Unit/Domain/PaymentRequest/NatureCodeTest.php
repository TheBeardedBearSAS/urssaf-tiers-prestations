<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;

final class NatureCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, NatureCode}>
     */
    public static function provideCodes(): iterable
    {
        yield '10' => ['10', NatureCode::HomeChildcareUnderThree];
        yield '20' => ['20', NatureCode::ChildAccompanimentUnderThree];
        yield '30' => ['30', NatureCode::DailyLivingAssistance];
        yield '40' => ['40', NatureCode::PersonalVehicleDriving];
        yield '50' => ['50', NatureCode::OutingAccompaniment];
        yield '60' => ['60', NatureCode::Housework];
        yield '70' => ['70', NatureCode::Gardening];
        yield '80' => ['80', NatureCode::HandymanWork];
        yield '90' => ['90', NatureCode::HomeChildcareOverSix];
        yield '100' => ['100', NatureCode::Tutoring];
        yield '110' => ['110', NatureCode::BeautyCareForDependents];
        yield '120' => ['120', NatureCode::MealPreparation];
        yield '130' => ['130', NatureCode::MealDelivery];
        yield '140' => ['140', NatureCode::LaundryDelivery];
        yield '150' => ['150', NatureCode::GroceryDelivery];
        yield '160' => ['160', NatureCode::ItAssistance];
        yield '170' => ['170', NatureCode::PetCare];
        yield '180' => ['180', NatureCode::HomeWatching];
        yield '190' => ['190', NatureCode::AdministrativeAssistance];
        yield '200' => ['200', NatureCode::ChildAccompanimentOverSix];
        yield '210' => ['210', NatureCode::RemoteAssistance];
        yield '220' => ['220', NatureCode::SignLanguageInterpreting];
        yield '230' => ['230', NatureCode::TemporaryDisabilityDriving];
        yield '240' => ['240', NatureCode::TemporaryDisabilityOutingAccompaniment];
        yield '250' => ['250', NatureCode::TemporaryDisabilityHomeHelp];
        yield '260' => ['260', NatureCode::CoordinationPlatform];
        yield '270' => ['270', NatureCode::Miscellaneous];
    }

    #[DataProvider('provideCodes')]
    public function testFromString(string $code, NatureCode $expected): void
    {
        static::assertSame($expected, NatureCode::fromString($code));
        static::assertSame($code, $expected->value);
        static::assertSame($expected !== NatureCode::Miscellaneous, $expected->isEligibleForTaxCredit());
    }

    public function testEveryCaseIsMapped(): void
    {
        static::assertCount(27, NatureCode::cases());
        static::assertFalse(NatureCode::Miscellaneous->isEligibleForTaxCredit());
        static::assertTrue(NatureCode::CoordinationPlatform->isEligibleForTaxCredit());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['15'];
        yield 'padded' => [' 10'];
        yield 'activity' => ['30A001'];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodesAreRejected(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::because('nature_code.unknown', 'Unknown nature code.'));

        NatureCode::fromString($code);
    }

    /**
     * @return iterable<string, array{NatureCode, ActivityCode, bool}>
     */
    public static function provideActivities(): iterable
    {
        yield 'housework night' => [NatureCode::Housework, ActivityCode::HouseworkNight, true];
        yield 'daily living fees' => [NatureCode::DailyLivingAssistance, ActivityCode::DailyLivingAdministrativeFees, true];
        yield 'tutoring fees' => [NatureCode::Tutoring, ActivityCode::TutoringAdministrativeFees, true];
        yield 'housework activity on daily living' => [NatureCode::DailyLivingAssistance, ActivityCode::HouseworkNight, false];
        yield 'tutoring activity on housework' => [NatureCode::Housework, ActivityCode::TutoringAdministrativeFees, false];
        yield 'nature without activities' => [NatureCode::Gardening, ActivityCode::HouseworkWeekend, false];
    }

    #[DataProvider('provideActivities')]
    public function testAllowsActivity(NatureCode $nature, ActivityCode $activity, bool $expected): void
    {
        static::assertSame($expected, $nature->allowsActivity($activity));
    }
}
