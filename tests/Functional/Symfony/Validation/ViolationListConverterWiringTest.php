<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

final class ViolationListConverterWiringTest extends TestCase
{
    public function testTheConverterIsPublicAndTranslatesWithTheShippedMessages(): void
    {
        $app = Harness::boot();
        $app->privateService('translator', LocaleAwareInterface::class)->setLocale('fr');
        $result = ValidationResult::of(Violation::error('birthDate', 'birth_date.in_future', 'The birth date must not be in the future.'));

        $violation = $app->get(ViolationListConverter::class)->convert($result, 'customer')->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('La date de naissance ne doit pas être dans le futur.', $violation->getMessage());
        static::assertSame('customer.birthDate', $violation->getPropertyPath());
    }
}
