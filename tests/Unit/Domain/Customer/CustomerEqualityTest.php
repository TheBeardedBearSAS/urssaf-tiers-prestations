<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Bic;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\ContactDetails;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipality;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\Civility;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\CivilStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;

/**
 * Value equality of a customer and of every part of it: equal when every part is equal, different as soon as one
 * differs (absent optional parts compare equal to each other only).
 *
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class CustomerEqualityTest extends TestCase
{
    public function testEqualCustomers(): void
    {
        static::assertTrue(CustomerFixtures::customer()->equals(CustomerFixtures::customer()));
        static::assertTrue(Customer::anonymised()->equals(Customer::anonymised()));
        static::assertFalse(CustomerFixtures::customer()->equals(Customer::anonymised()));
    }

    public function testCustomersDifferingByOnePartAreDifferent(): void
    {
        $customer = CustomerFixtures::customer();
        $other = Customer::anonymised();

        static::assertFalse($customer->equals(Customer::of($other->civilStatus(), $customer->contactDetails(), $customer->bankAccount())));
        static::assertFalse($customer->equals(Customer::of($customer->civilStatus(), $other->contactDetails(), $customer->bankAccount())));
        static::assertFalse($customer->equals(Customer::of($customer->civilStatus(), $customer->contactDetails(), $other->bankAccount())));
    }

    /**
     * @return iterable<string, array{string}> keys of {@see self::civilStatusDifferences()}
     */
    public static function provideCivilStatusDifferences(): iterable
    {
        yield 'civility' => ['civility'];
        yield 'birth name' => ['birth name'];
        yield 'usage name' => ['usage name'];
        yield 'no usage name' => ['no usage name'];
        yield 'first names' => ['first names'];
        yield 'birth date' => ['birth date'];
        yield 'birth place' => ['birth place'];
    }

    /**
     * @return array<string, CivilStatus>
     *
     * @throws InvalidValue
     */
    private static function civilStatusDifferences(): array
    {
        return [
            'civility' => self::civilStatus(['civility' => Civility::Female]),
            'birth name' => self::civilStatus(['birthName' => PersonName::birthName('Dupont')]),
            'usage name' => self::civilStatus(['usageName' => PersonName::usageName('Dupont')]),
            'no usage name' => self::civilStatus(['usageName' => null]),
            'first names' => self::civilStatus(['firstNames' => PersonName::firstNames('Jean')]),
            'birth date' => self::civilStatus(['birthDate' => CalendarDate::fromString('1980-03-30')]),
            'birth place' => self::civilStatus(['birthPlace' => BirthPlace::abroad(CountryCode::fromString('99134'))]),
        ];
    }

    #[DataProvider('provideCivilStatusDifferences')]
    public function testCivilStatusesDifferingByOnePartAreDifferent(string $case): void
    {
        $other = self::civilStatusDifferences()[$case];
        static::assertTrue(self::civilStatus()->equals(self::civilStatus()));
        static::assertFalse(self::civilStatus()->equals($other));
        static::assertFalse($other->equals(self::civilStatus()));
    }

    public function testCivilStatusesWithoutUsageNameAreEqual(): void
    {
        static::assertTrue(self::civilStatus(['usageName' => null])->equals(self::civilStatus(['usageName' => null])));
    }

    public function testBirthPlaces(): void
    {
        $lyon = BirthPlace::fromInseeMunicipality('69101', 'Lyon 1er');

        static::assertTrue($lyon->equals(BirthPlace::fromInseeMunicipality('69101', 'Lyon 1er')));
        static::assertFalse($lyon->equals(BirthPlace::fromInseeMunicipality('01101', 'Lyon 1er')));
        static::assertFalse($lyon->equals(BirthPlace::fromInseeMunicipality('69102', 'Lyon 1er')));
        static::assertFalse($lyon->equals(BirthPlace::fromInseeMunicipality('69101', 'Lyon')));
        static::assertFalse($lyon->equals(BirthPlace::abroad(CountryCode::fromString('99134'))));
        static::assertTrue(BirthPlace::abroad(CountryCode::fromString('99134'))->equals(BirthPlace::abroad(CountryCode::fromString('99134'))));
        static::assertFalse(BirthPlace::abroad(CountryCode::fromString('99134'))->equals(BirthPlace::abroad(CountryCode::fromString('99135'))));
    }

    public function testBirthMunicipalities(): void
    {
        $municipality = BirthMunicipality::of(BirthMunicipalityCode::fromString('101'), MunicipalityName::ofBirthPlace('Lyon'));

        static::assertTrue($municipality->equals(BirthMunicipality::fromInseeCode('69101', 'Lyon')));
        static::assertFalse($municipality->equals(BirthMunicipality::fromInseeCode('69102', 'Lyon')));
        static::assertFalse($municipality->equals(BirthMunicipality::fromInseeCode('69101', 'Lyon 1er')));
    }

    /**
     * @return iterable<string, array{string}> keys of {@see self::streetDifferences()}
     */
    public static function provideStreetDifferences(): iterable
    {
        yield 'number' => ['number'];
        yield 'letter' => ['letter'];
        yield 'no letter' => ['no letter'];
        yield 'type' => ['type'];
        yield 'name' => ['name'];
        yield 'complement' => ['complement'];
        yield 'no complement' => ['no complement'];
        yield 'locality' => ['locality'];
        yield 'no locality' => ['no locality'];
        yield 'locality only' => ['locality only'];
    }

    /**
     * @return array<string, StreetAddress>
     *
     * @throws InvalidValue
     */
    private static function streetDifferences(): array
    {
        return [
            'number' => self::street(['number' => StreetNumber::fromString('9')]),
            'letter' => self::street(['letter' => StreetLetter::fromString('T')]),
            'no letter' => self::street(['letter' => null]),
            'type' => self::street(['typeCode' => StreetTypeCode::fromString('AV')]),
            'name' => self::street(['name' => AddressText::streetName('de la Lune')]),
            'complement' => self::street(['complement' => AddressText::complement('Batiment B')]),
            'no complement' => self::street(['complement' => null]),
            'locality' => self::street(['locality' => AddressText::locality('Le Bourg')]),
            'no locality' => self::street(['locality' => null]),
            'locality only' => new StreetAddress(null, null, null, null, null, AddressText::locality('Le Beyssat')),
        ];
    }

    #[DataProvider('provideStreetDifferences')]
    public function testStreetAddressesDifferingByOnePartAreDifferent(string $case): void
    {
        $other = self::streetDifferences()[$case];
        static::assertTrue(self::street()->equals(self::street()));
        static::assertTrue($other->equals($other));
        static::assertFalse(self::street()->equals($other));
        static::assertFalse($other->equals(self::street()));
    }

    public function testPostalAddressesDifferingByOnePartAreDifferent(): void
    {
        $address = self::postalAddress([]);

        static::assertTrue($address->equals(self::postalAddress([])));
        static::assertFalse($address->equals(self::postalAddress(['street' => new StreetAddress(null, null, null, null, null, AddressText::locality('Le Bourg'))])));
        static::assertFalse($address->equals(self::postalAddress(['municipalityName' => MunicipalityName::ofAddress('LYON 02')])));
        static::assertFalse($address->equals(self::postalAddress(['municipalityCode' => MunicipalityCode::fromString('69102')])));
        static::assertFalse($address->equals(self::postalAddress(['postalCode' => PostalCode::fromString('69002')])));
        static::assertFalse($address->equals(self::postalAddress(['countryCode' => CountryCode::fromString('99134')])));
    }

    public function testContactDetailsDifferingByOnePartAreDifferent(): void
    {
        $contact = CustomerFixtures::customer()->contactDetails();
        $phone = $contact->mobilePhone();
        $email = $contact->email();
        $address = $contact->address();

        static::assertTrue($contact->equals(ContactDetails::of($phone, $email, $address)));
        static::assertFalse($contact->equals(ContactDetails::of(MobilePhoneNumber::fromString('0605040303'), $email, $address)));
        static::assertFalse($contact->equals(ContactDetails::of($phone, EmailAddress::fromString('other@contact.fr'), $address)));
        static::assertFalse($contact->equals(ContactDetails::of($phone, $email, Customer::anonymised()->contactDetails()->address())));
    }

    public function testBankAccountsDifferingByOnePartAreDifferent(): void
    {
        $account = CustomerFixtures::customer()->bankAccount();
        $iban = $account->iban();
        $bic = $account->bic();
        $holder = $account->holder();

        static::assertTrue($account->equals(BankAccount::of($iban, $bic, $holder)));
        static::assertFalse($account->equals(BankAccount::of(Iban::fromString('FR7600000000000000000000000'), $bic, $holder)));
        static::assertFalse($account->equals(BankAccount::of($iban, Bic::fromString('BNPAFRPP'), $holder)));
        static::assertFalse($account->equals(BankAccount::of($iban, $bic, AccountHolder::fromString('M Jean Martin'))));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function civilStatus(array $overrides = []): CivilStatus
    {
        $parts = $overrides + [
            'civility' => Civility::Male,
            'birthName' => PersonName::birthName('Durand'),
            'usageName' => PersonName::usageName('Martin'),
            'firstNames' => PersonName::firstNames('Eric'),
            'birthDate' => CalendarDate::fromString('1980-03-29'),
            'birthPlace' => BirthPlace::fromInseeMunicipality('69101', 'Lyon'),
        ];
        Assert::assertInstanceOf(Civility::class, $parts['civility']);
        Assert::assertInstanceOf(PersonName::class, $parts['birthName']);
        \assert($parts['usageName'] === null || $parts['usageName'] instanceof PersonName, 'Unexpected override type.');
        Assert::assertInstanceOf(PersonName::class, $parts['firstNames']);
        Assert::assertInstanceOf(CalendarDate::class, $parts['birthDate']);
        Assert::assertInstanceOf(BirthPlace::class, $parts['birthPlace']);

        return new CivilStatus($parts['civility'], $parts['birthName'], $parts['usageName'], $parts['firstNames'], $parts['birthDate'], $parts['birthPlace']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function street(array $overrides = []): StreetAddress
    {
        $parts = $overrides + [
            'number' => StreetNumber::fromString('8'),
            'letter' => StreetLetter::fromString('B'),
            'typeCode' => StreetTypeCode::fromString('R'),
            'name' => AddressText::streetName('du Soleil'),
            'complement' => AddressText::complement('Batiment A'),
            'locality' => AddressText::locality('Le Beyssat'),
        ];
        Assert::assertInstanceOf(StreetNumber::class, $parts['number']);
        \assert($parts['letter'] === null || $parts['letter'] instanceof StreetLetter, 'Unexpected override type.');
        Assert::assertInstanceOf(StreetTypeCode::class, $parts['typeCode']);
        Assert::assertInstanceOf(AddressText::class, $parts['name']);
        \assert($parts['complement'] === null || $parts['complement'] instanceof AddressText, 'Unexpected override type.');
        \assert($parts['locality'] === null || $parts['locality'] instanceof AddressText, 'Unexpected override type.');

        return new StreetAddress($parts['number'], $parts['letter'], $parts['typeCode'], $parts['name'], $parts['complement'], $parts['locality']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function postalAddress(array $overrides): PostalAddress
    {
        $parts = $overrides + [
            'street' => self::street(),
            'municipalityName' => MunicipalityName::ofAddress('LYON 01'),
            'municipalityCode' => MunicipalityCode::fromString('69101'),
            'postalCode' => PostalCode::fromString('69001'),
            'countryCode' => CountryCode::france(),
        ];
        Assert::assertInstanceOf(StreetAddress::class, $parts['street']);
        Assert::assertInstanceOf(MunicipalityName::class, $parts['municipalityName']);
        Assert::assertInstanceOf(MunicipalityCode::class, $parts['municipalityCode']);
        Assert::assertInstanceOf(PostalCode::class, $parts['postalCode']);
        Assert::assertInstanceOf(CountryCode::class, $parts['countryCode']);

        return PostalAddress::of($parts['street'], $parts['municipalityName'], $parts['municipalityCode'], $parts['postalCode'], $parts['countryCode']);
    }
}
