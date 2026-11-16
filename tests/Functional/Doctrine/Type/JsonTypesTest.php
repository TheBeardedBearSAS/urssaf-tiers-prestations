<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\DepositType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\RejectionCausesType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\ServiceLinesType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafTrackingType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafTracking;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\Samples;

/**
 * Conversion scenarios of the JSON column types: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class JsonTypesTest extends TestCase
{
    private const ERRORS_TYPE = 'list<' . SubmissionError::class . '>';

    private const CAUSES_TYPE = 'list<' . RejectionCause::class . '>';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string}> case names of {@see self::document()}
     */
    public static function documents(): iterable
    {
        yield 'deposit' => ['deposit'];
        yield 'submission errors' => ['errors'];
        yield 'no submission error' => ['no-errors'];
        yield 'rejection causes' => ['causes'];
        yield 'no rejection cause' => ['no-causes'];
        yield 'empty tracking' => ['tracking'];
        yield 'no service line' => ['no-lines'];
    }

    /**
     * @return array{string, object|list<SubmissionError>|list<RejectionCause>, string, string} type name, value, expected JSON, description of the PHP type
     *
     * @throws InvalidValue
     */
    private static function document(string $case): array
    {
        return match ($case) {
            'deposit' => [
                DepositType::NAME,
                Deposit::of(Money::fromCents(275), CalendarDate::fromString('2022-02-01')),
                '{"amount":275,"paymentDate":"2022-02-01"}',
                Deposit::class,
            ],
            'errors' => [
                SubmissionErrorsType::NAME,
                [SubmissionError::of('ERR_A', 'message', 'description')],
                '[{"code":"ERR_A","message":"message","description":"description"}]',
                self::ERRORS_TYPE,
            ],
            'no-errors' => [SubmissionErrorsType::NAME, [], '[]', self::ERRORS_TYPE],
            'causes' => [
                RejectionCausesType::NAME,
                [RejectionCause::of('ERR_A', 'message'), RejectionCause::of('ERR_B', 'other')],
                '[{"code":"ERR_A","message":"message"},{"code":"ERR_B","message":"other"}]',
                self::CAUSES_TYPE,
            ],
            'no-causes' => [RejectionCausesType::NAME, [], '[]', self::CAUSES_TYPE],
            'tracking' => [
                UrssafTrackingType::NAME,
                UrssafTracking::none(),
                '{"paymentRequestId":null,"history":[],"transfer":null,"rejection":null,"lastPolledAt":null}',
                UrssafTracking::class,
            ],
            default => [ServiceLinesType::NAME, ServiceLines::of(), '[]', ServiceLines::class],
        };
    }

    #[DataProvider('documents')]
    public function testItStoresAnExplicitDocumentAndRebuildsTheValue(string $case): void
    {
        [$name, $value, $json] = self::document($case);
        $type = Type::getType($name);

        static::assertSame($json, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($json, $this->platform));
    }

    #[DataProvider('documents')]
    public function testNullStaysNull(string $case): void
    {
        [$name] = self::document($case);
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('documents')]
    public function testItRefusesAForeignPhpValue(string $case): void
    {
        [$name, , , $expected] = self::document($case);

        try {
            Type::getType($name)->convertToDatabaseValue('invalid', $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . $expected . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('documents')]
    public function testItRefusesAStoredValueThatIsNotValidJsonObjectOrArray(string $case): void
    {
        [$name] = self::document($case);
        $messages = [];
        foreach ([12, '{not json', '12', '"text"'] as $stored) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        static::assertStringEndsWith('types: string, null.', $messages[0]);
        static::assertStringContainsString('Expected format "JSON"', $messages[1]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[2]);
        static::assertStringContainsString('Expected format "JSON object or array"', $messages[3]);
    }

    public function testAJsonDocumentIsDeclaredAsAJsonColumn(): void
    {
        static::assertSame(
            $this->platform->getJsonTypeDeclarationSQL([]),
            Type::getType(ServiceLinesType::NAME)->getSQLDeclaration([], $this->platform),
        );
    }

    public function testItRefusesAListThatIsNotAListOfSubmissionErrors(): void
    {
        $type = Type::getType(SubmissionErrorsType::NAME);
        $error = SubmissionError::of('ERR_A', 'm', 'd');

        foreach ([['a' => $error], [$error, 'text'], [1 => $error]] as $value) {
            try {
                $type->convertToDatabaseValue($value, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertStringEndsWith('types: ' . self::ERRORS_TYPE . ', null.', $exception->getMessage());
            }
        }
    }

    public function testItRefusesAListThatIsNotAListOfRejectionCauses(): void
    {
        $type = Type::getType(RejectionCausesType::NAME);
        $cause = RejectionCause::of('ERR_A', 'm');

        foreach ([['a' => $cause], [$cause, 'text'], [1 => $cause]] as $value) {
            try {
                $type->convertToDatabaseValue($value, $this->platform);
                static::fail('Expected a conversion failure.');
            } catch (ConversionException $exception) {
                static::assertStringEndsWith('types: ' . self::CAUSES_TYPE . ', null.', $exception->getMessage());
            }
        }
    }

    public function testStoredContentThatTheDomainRefusesIsReportedAsAConversionFailure(): void
    {
        $cases = [
            DepositType::NAME => '{"amount":0,"paymentDate":"2022-02-01"}',
            SubmissionErrorsType::NAME => '[{"code":"","message":"m","description":"d"}]',
            RejectionCausesType::NAME => '[{"code":"","message":"m"}]',
            UrssafTrackingType::NAME => '{"paymentRequestId":"","history":[],"transfer":null,"rejection":null,"lastPolledAt":null}',
            ServiceLinesType::NAME => '[{"nature":"999","activity":null,"unit":"HEURE","quantity":1000,"unitPrice":1000,"totalInclTax":1,"totalExclTax":1,"vat":0,"intervenant":"SAP123456789","period":null,"complement":null}]',
        ];
        foreach ($cases as $name => $stored) {
            try {
                Type::getType($name)->convertToPHPValue($stored, $this->platform);
                static::fail('Expected a conversion failure for ' . $name);
            } catch (ConversionException $exception) {
                static::assertNotNull($exception->getPrevious());
            }
        }
    }

    public function testTheTrackingOfAFollowedRequestIsRebuiltThroughItsPublicOperations(): void
    {
        $tracking = Samples::followedPaymentRequest()->tracking();
        $type = new UrssafTrackingType();

        $json = $type->convertToDatabaseValue($tracking, $this->platform);
        $rebuilt = $type->convertToPHPValue($json, $this->platform);

        static::assertEquals($tracking, $rebuilt);
        static::assertSame(
            '{"paymentRequestId":"urssaf-official","history":[{"code":"10","observedAt":"2022-03-01T00:00:20Z"},{"code":"20","observedAt":"2022-03-02T00:00:00Z"},{"code":"70","observedAt":"2022-03-05T00:00:00Z"}],'
            . '"transfer":{"amount":1102,"valueDate":"2022-03-10"},"rejection":{"code":"SOME_CODE","comment":"Free text, with accents: \\u00e9 \\u00e0"},"lastPolledAt":"2022-03-05T00:00:00Z"}',
            $json,
        );
    }

    public function testATrackingWithoutPollInstantIgnoresTransferAndRejection(): void
    {
        $type = new UrssafTrackingType();
        $json = '{"paymentRequestId":"u-1","history":[{"code":"10","observedAt":"2022-03-01T00:00:20Z"}],"transfer":{"amount":5,"valueDate":"2022-03-10"},"rejection":{"code":"R","comment":""},"lastPolledAt":null}';

        $tracking = $type->convertToPHPValue($json, $this->platform);

        static::assertInstanceOf(UrssafTracking::class, $tracking);
        static::assertNull($tracking->transfer());
        static::assertNull($tracking->rejection());
        static::assertNull($tracking->lastPolledAt());
        static::assertSame('u-1', $tracking->paymentRequestId()?->value());
        static::assertSame('10', $tracking->current()?->rawCode());
    }
}
