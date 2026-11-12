<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ViolationListConverterTest extends TestCase
{
    public function testEveryErrorBecomesAConstraintViolationWithTheCodeAndThePath(): void
    {
        $error = Violation::error('invoice.lines[0].quantity', 'quantity.not_positive', 'The quantity must be strictly positive.');

        $list = (new ViolationListConverter(null))->convert(ValidationResult::of($error));

        static::assertCount(1, $list);
        $violation = self::first($list);
        static::assertSame('The quantity must be strictly positive.', $violation->getMessage());
        static::assertSame('quantity.not_positive', $violation->getMessageTemplate());
        static::assertSame('quantity.not_positive', $violation->getCode());
        static::assertSame('invoice.lines[0].quantity', $violation->getPropertyPath());
        static::assertSame([], $violation->getParameters());
        static::assertNull($violation->getRoot());
        static::assertNull($violation->getInvalidValue());
        static::assertNull($violation->getPlural());
        static::assertNull($violation->getConstraint());
        static::assertSame($error, $violation->getCause());
    }

    public function testTheViolationsOfAValidationFailureAreConverted(): void
    {
        $result = ValidationResult::of(
            Violation::error('birthDate', 'birth_date.in_future', 'The birth date must not be in the future.'),
            Violation::error('email', 'email_address.invalid_format', 'Email address has an invalid format.'),
        );

        $list = (new ViolationListConverter(null))->convert(ValidationFailed::withResult($result));

        static::assertSame(['birthDate', 'email'], self::paths($list));
    }

    public function testThePathPrefixIsPrependedToEveryPath(): void
    {
        $result = ValidationResult::of(
            Violation::error('address.postalCode', 'postal_code.empty', 'Postal code is required.'),
            Violation::error('', 'validation.failed', 'The data is not valid.'),
        );

        $list = (new ViolationListConverter(null))->convert($result, 'customer');

        static::assertSame(['customer.address.postalCode', 'customer'], self::paths($list));
    }

    public function testWarningsAreKeptOnlyOnRequest(): void
    {
        $result = ValidationResult::of(
            Violation::warning('activityCode', 'activity_code.unknown', 'Unknown activity code.'),
            Violation::error('natureCode', 'nature_code.unknown', 'Unknown nature code.'),
        );
        $converter = new ViolationListConverter(null);

        static::assertSame(['natureCode'], self::paths($converter->convert($result)));
        static::assertSame(['activityCode', 'natureCode'], self::paths($converter->convertWithWarnings($result, '')));
    }

    public function testTheParametersBecomePlaceholdersAndAreGivenToTheTranslator(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'payment_request_submission.too_many_items' => 'Au plus %max% (%ratio%, %allowed%, %blocked%, %unit%).',
        ], 'fr', 'urssaf_tiers_prestations');
        $translator->addResource('array', ['payment_request_submission.too_many_items' => 'Wrong domain.'], 'fr', 'messages');
        $error = Violation::error('lines', 'payment_request_submission.too_many_items', 'At most 10 payment requests can be submitted at once.', [
            'max' => 10,
            'ratio' => 0.5,
            'allowed' => true,
            'blocked' => false,
            'unit' => 'HEURE',
        ]);

        $violation = self::first((new ViolationListConverter($translator))->convert(ValidationResult::of($error)));

        $parameters = ['%max%' => '10', '%ratio%' => '0.5', '%allowed%' => 'true', '%blocked%' => 'false', '%unit%' => 'HEURE'];
        static::assertSame('Au plus 10 (0.5, true, false, HEURE).', $violation->getMessage());
        static::assertSame($parameters, $violation->getParameters());
    }

    private static function first(ConstraintViolationList $list): ConstraintViolation
    {
        $violation = $list->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);

        return $violation;
    }

    /**
     * @return list<string>
     */
    private static function paths(ConstraintViolationList $list): array
    {
        $paths = [];
        foreach ($list as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
