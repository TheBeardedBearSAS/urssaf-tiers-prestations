<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;

/**
 * How URSSAF contacts the customer: mobile phone, e-mail and postal address.
 */
final readonly class ContactDetails
{
    private function __construct(
        private MobilePhoneNumber $mobilePhone,
        private EmailAddress $email,
        private PostalAddress $address,
    ) {}

    public static function of(MobilePhoneNumber $mobilePhone, EmailAddress $email, PostalAddress $address): self
    {
        return new self($mobilePhone, $email, $address);
    }

    public function mobilePhone(): MobilePhoneNumber
    {
        return $this->mobilePhone;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function address(): PostalAddress
    {
        return $this->address;
    }

    public function equals(self $other): bool
    {
        return $this->mobilePhone->equals($other->mobilePhone)
            && $this->email->equals($other->email)
            && $this->address->equals($other->address);
    }
}
