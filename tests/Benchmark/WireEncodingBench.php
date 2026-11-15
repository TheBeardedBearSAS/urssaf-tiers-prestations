<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Benchmark;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\CustomerRegistrationMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

/**
 * Encoding of the request bodies sent to URSSAF: a customer registration (010) and a full batch of payment
 * requests (050, at most 10 per call), down to the JSON string.
 */
final readonly class WireEncodingBench
{
    private CustomerRegistrationMapper $registrationMapper;

    private PaymentRequestMapper $paymentRequestMapper;

    private CustomerRegistration $registration;

    /** @var list<PaymentRequest> */
    private array $batch;

    public function __construct()
    {
        $this->registrationMapper = new CustomerRegistrationMapper();
        $this->paymentRequestMapper = new PaymentRequestMapper();
        $this->registration = CustomerFixtures::newRegistration();
        $this->batch = array_map(Fixtures::pendingRequest(...), range(1, 10));
    }

    public function benchEncodeACustomerRegistration(): void
    {
        JsonEncoder::encode($this->registrationMapper->toBody($this->registration));
    }

    public function benchEncodeABatchOfTenPaymentRequests(): void
    {
        JsonEncoder::encode($this->paymentRequestMapper->toBody(...$this->batch));
    }
}
