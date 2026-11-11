<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\CustomerRegistrationMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Customers;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Json;

final class CustomerRegistrationMapperTest extends TestCase
{
    public function testItEncodesTheOfficialExampleLikeTheGoldenFixture(): void
    {
        $body = (new CustomerRegistrationMapper())->toBody(CustomerFixtures::newRegistration());

        static::assertSame(
            json_decode(Fixtures::load('010-register-request'), true, 512, JSON_THROW_ON_ERROR),
            json_decode(JsonEncoder::encode($body), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAnAbsentUsageNameIsOmitted(): void
    {
        $draft = Customers::draft(CustomerFixtures::officialDraft()->birthPlace, CustomerFixtures::officialAddress(), null);

        $body = (new CustomerRegistrationMapper())->toBody(Customers::registration($draft));

        static::assertArrayNotHasKey('nomUsage', $body);
        static::assertSame('Durand', $body['nomNaissance']);
    }

    public function testBirthAbroadHasNoDepartmentNorMunicipality(): void
    {
        $draft = Customers::draft(new BirthPlaceDraft('99350', null, null, null), CustomerFixtures::officialAddress(), 'Martin');

        $body = (new CustomerRegistrationMapper())->toBody(Customers::registration($draft));

        static::assertSame(['codePaysNaissance' => '99350'], $body['lieuNaissance']);
    }

    public function testUnsetAddressPartsAreOmittedAndNeverSentAsNull(): void
    {
        $address = new AddressDraft(null, null, null, null, null, 'Le Beyssat', 'LYON 01', '69101', '69001', '99100');
        $draft = Customers::draft(CustomerFixtures::officialDraft()->birthPlace, $address, 'Martin');

        $body = (new CustomerRegistrationMapper())->toBody(Customers::registration($draft));

        static::assertSame(
            [
                'lieuDit' => 'Le Beyssat',
                'libelleCommune' => 'LYON 01',
                'codeCommune' => '69101',
                'codePostal' => '69001',
                'codePays' => '99100',
            ],
            $body['adressePostale'],
        );
    }

    public function testAnAddressWithoutLocalityOmitsIt(): void
    {
        $address = new AddressDraft('8', 'B', 'R', 'du Soleil', 'Batiment A', null, 'LYON 01', '69101', '69001', '99100');
        $draft = Customers::draft(CustomerFixtures::officialDraft()->birthPlace, $address, 'Martin');

        $body = (new CustomerRegistrationMapper())->toBody(Customers::registration($draft));

        static::assertSame(
            [
                'numeroVoie' => '8',
                'lettreVoie' => 'B',
                'codeTypeVoie' => 'R',
                'libelleVoie' => 'du Soleil',
                'complement' => 'Batiment A',
                'libelleCommune' => 'LYON 01',
                'codeCommune' => '69101',
                'codePostal' => '69001',
                'codePays' => '99100',
            ],
            $body['adressePostale'],
        );
    }

    public function testItNeverSendsInternalIdentifiers(): void
    {
        $json = JsonEncoder::encode((new CustomerRegistrationMapper())->toBody(CustomerFixtures::newRegistration()));

        static::assertStringNotContainsString('reg-1', $json);
        static::assertStringNotContainsString('tenant-1', $json);
        static::assertStringNotContainsString('customer-42', $json);
    }

    public function testItReadsTheCustomerIdOfTheResponse(): void
    {
        $id = (new CustomerRegistrationMapper())->mapResponse(Json::fixture('register_customer_response'));

        static::assertSame('af0be430-908a-11eb-a8b3-0242ac130003', $id->value());
    }

    public function testAMissingCustomerIdIsAnUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new CustomerRegistrationMapper())->mapResponse(Json::payload(['other' => 'x']));
    }

    public function testAMalformedCustomerIdIsAnUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        (new CustomerRegistrationMapper())->mapResponse(Json::payload(['idClient' => 'not a uuid']));
    }

    public function testItKeepsTheWireKeyOrderOfTheSpecification(): void
    {
        $body = (new CustomerRegistrationMapper())->toBody(CustomerFixtures::newRegistration());

        static::assertSame(
            [
                'civilite',
                'nomNaissance',
                'nomUsage',
                'prenoms',
                'dateNaissance',
                'lieuNaissance',
                'numeroTelephonePortable',
                'adresseMail',
                'adressePostale',
                'coordonneeBancaire',
            ],
            array_keys($body),
        );
    }
}
