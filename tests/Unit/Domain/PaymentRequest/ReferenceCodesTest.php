<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\RejectionContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\RejectionReason;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;

final class ReferenceCodesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ActivityCode, NatureCode}>
     */
    public static function provideActivities(): iterable
    {
        yield '30A001' => ['30A001', ActivityCode::DailyLivingAdministrativeFees, NatureCode::DailyLivingAssistance];
        yield '30A002' => ['30A002', ActivityCode::DailyLivingNight, NatureCode::DailyLivingAssistance];
        yield '30A003' => ['30A003', ActivityCode::DailyLivingWeekend, NatureCode::DailyLivingAssistance];
        yield '60A001' => ['60A001', ActivityCode::HouseworkAdministrativeFees, NatureCode::Housework];
        yield '60A002' => ['60A002', ActivityCode::HouseworkNight, NatureCode::Housework];
        yield '60A003' => ['60A003', ActivityCode::HouseworkWeekend, NatureCode::Housework];
        yield '100A001' => ['100A001', ActivityCode::TutoringAdministrativeFees, NatureCode::Tutoring];
    }

    #[DataProvider('provideActivities')]
    public function testActivities(string $code, ActivityCode $activity, NatureCode $nature): void
    {
        static::assertSame($activity, ActivityCode::fromString($code));
        static::assertSame($nature, $activity->nature());
        static::assertTrue($nature->allowsActivity($activity));
        static::assertCount(7, ActivityCode::cases());
    }

    public function testUnknownActivityIsRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::because('activity_code.unknown', 'Unknown activity code.'));

        ActivityCode::fromString('60A004');
    }

    public function testUnits(): void
    {
        static::assertSame(Unit::Hour, Unit::fromString('HEURE'));
        static::assertSame(Unit::Flat, Unit::fromString('FORFAIT'));
        static::assertCount(2, Unit::cases());
    }

    public function testUnknownUnitIsRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::because('unit.unknown', 'Unknown unit.'));

        Unit::fromString('heure');
    }

    /**
     * @return iterable<string, array{string, RejectionReason, RejectionContext}>
     */
    public static function provideRejectionReasons(): iterable
    {
        yield 'REGUL_MNT_FACT' => ['REGUL_MNT_FACT', RejectionReason::RegularisationWrongAmount, RejectionContext::Regularisation];
        yield 'REGUL_NB_QTE_FACT' => ['REGUL_NB_QTE_FACT', RejectionReason::RegularisationWrongQuantity, RejectionContext::Regularisation];
        yield 'REGUL_PRESTA_FACT' => ['REGUL_PRESTA_FACT', RejectionReason::RegularisationWrongServices, RejectionContext::Regularisation];
        yield 'REGUL_AUTRE' => ['REGUL_AUTRE', RejectionReason::RegularisationOther, RejectionContext::Regularisation];
        yield 'CONTEST_ERR_FACT' => ['CONTEST_ERR_FACT', RejectionReason::ContestationInvoiceError, RejectionContext::Contestation];
        yield 'CONTEST_FACT_REGLEE' => ['CONTEST_FACT_REGLEE', RejectionReason::ContestationAlreadyPaid, RejectionContext::Contestation];
        yield 'CONTEST_AUTRE' => ['CONTEST_AUTRE', RejectionReason::ContestationOther, RejectionContext::Contestation];
        yield 'ANNUL_DBL' => ['ANNUL_DBL', RejectionReason::CancellationDuplicate, RejectionContext::Cancellation];
        yield 'ANNUL_ERR_MNT' => ['ANNUL_ERR_MNT', RejectionReason::CancellationWrongAmount, RejectionContext::Cancellation];
        yield 'ANNUL_ERR_TECH' => ['ANNUL_ERR_TECH', RejectionReason::CancellationTechnicalError, RejectionContext::Cancellation];
        yield 'ANNUL_AUTRE' => ['ANNUL_AUTRE', RejectionReason::CancellationOther, RejectionContext::Cancellation];
    }

    #[DataProvider('provideRejectionReasons')]
    public function testRejectionReasons(string $code, RejectionReason $reason, RejectionContext $context): void
    {
        static::assertSame($reason, RejectionReason::from($code));
        static::assertSame($context, $reason->context());
        static::assertCount(11, RejectionReason::cases());
    }

    public function testRejectionContexts(): void
    {
        static::assertSame('regularisation', RejectionContext::Regularisation->value);
        static::assertSame('contestation', RejectionContext::Contestation->value);
        static::assertSame('cancellation', RejectionContext::Cancellation->value);
    }
}
