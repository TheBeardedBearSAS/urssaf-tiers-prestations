<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Benchmark;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

/**
 * Local validation of a customer (identity, birth place, address, bank account) before registration.
 */
final readonly class CustomerValidationBench
{
    private CustomerFactory $factory;

    private CustomerDraft $draft;

    public function __construct()
    {
        $this->factory = new CustomerFactory();
        $this->draft = CustomerFixtures::officialDraft();
    }

    public function benchValidateADraft(): void
    {
        $this->factory->validate($this->draft);
    }

    public function benchBuildACustomerFromADraft(): void
    {
        $this->factory->fromDraft($this->draft);
    }
}
