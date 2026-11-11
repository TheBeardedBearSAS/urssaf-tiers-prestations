<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\ActivityBelongsToNature;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\LineTotalMatchesUnitPrice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\LineVatIsConsistent;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\NatureIsEligibleForTaxCredit;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class LineSpecificationsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideVatAmounts(): iterable
    {
        yield 'exact' => ['5.01', '0.50', []];
        yield '0.05 below' => ['4.96', '0.50', []];
        yield '0.06 below' => ['4.95', '0.50', ['error amounts.vatAmount line.vat_mismatch']];
        yield '0.05 above' => ['5.01', '0.55', []];
        yield '0.06 above' => ['5.01', '0.56', ['error amounts.vatAmount line.vat_mismatch']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideVatAmounts')]
    public function testLineVatIsConsistent(string $exclTax, string $vat, array $expected): void
    {
        $line = Fixtures::line('1', '5.51', '5.51', $exclTax, $vat);

        static::assertSame($expected, ViolationAssertions::summary((new LineVatIsConsistent())->validate($line)));
    }

    /**
     * @return iterable<string, array{string, string, string, list<string>}>
     */
    public static function provideLineTotals(): iterable
    {
        yield 'exact' => ['1', '5.51', '5.51', []];
        yield '3-decimal unit price, rounded half up' => ['1.75', '22.335', '39.09', []];
        yield '0.05 below' => ['1.75', '22.335', '39.04', []];
        yield '0.06 below' => ['1.75', '22.335', '39.03', ['error amounts.totalInclTax line.total_mismatch']];
        yield '0.05 above' => ['1.75', '22.335', '39.14', []];
        yield '0.06 above' => ['1.75', '22.335', '39.15', ['error amounts.totalInclTax line.total_mismatch']];
        yield 'quantity is taken into account' => ['2', '5.515', '11.03', []];
        yield 'quantity ignored' => ['2', '5.515', '5.52', ['error amounts.totalInclTax line.total_mismatch']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideLineTotals')]
    public function testLineTotalMatchesUnitPrice(string $quantity, string $unitPrice, string $total, array $expected): void
    {
        $line = Fixtures::line($quantity, $unitPrice, $total, $total, '0');

        static::assertSame($expected, ViolationAssertions::summary((new LineTotalMatchesUnitPrice())->validate($line)));
    }

    public function testActivityBelongsToNature(): void
    {
        $specification = new ActivityBelongsToNature();

        static::assertSame([], $specification->validate(Fixtures::typedLine(NatureCode::Housework, null, null))->all());
        static::assertSame([], $specification->validate(Fixtures::typedLine(NatureCode::Housework, ActivityCode::HouseworkNight, null))->all());

        $result = $specification->validate(Fixtures::typedLine(NatureCode::Housework, ActivityCode::DailyLivingNight, null));
        static::assertSame(['error activityCode line.activity_not_in_nature'], ViolationAssertions::summary($result));
        static::assertSame(['nature_code' => '60', 'activity_code' => '30A002'], $result->all()[0]->params());
    }

    public function testNatureIsEligibleForTaxCredit(): void
    {
        $specification = new NatureIsEligibleForTaxCredit();

        static::assertSame([], $specification->validate(Fixtures::typedLine(NatureCode::CoordinationPlatform, null, null))->all());

        $result = $specification->validate(Fixtures::typedLine(NatureCode::Miscellaneous, null, null));
        static::assertSame(['warning natureCode line.not_eligible'], ViolationAssertions::summary($result));
        static::assertTrue($result->isValid());
    }
}
