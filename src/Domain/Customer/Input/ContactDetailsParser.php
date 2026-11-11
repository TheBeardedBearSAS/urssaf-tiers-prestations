<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\ContactDetails;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;

/**
 * Parses the contact fields of a raw customer: mobilePhone, email, address.*.
 *
 * @internal used by {@see CustomerFactory}
 */
final readonly class ContactDetailsParser
{
    /**
     * Returns the contact details, or null after reporting the violations to the collector.
     */
    public function parse(CustomerDraft $draft, ViolationCollector $collector): ?ContactDetails
    {
        $mobilePhone = $collector->required(
            'mobilePhone',
            'mobile_phone',
            $draft->mobilePhone,
            MobilePhoneNumber::fromString(...),
        );
        $email = $collector->required('email', 'email', $draft->email, EmailAddress::fromString(...));
        $addressCollector = new ViolationCollector();
        $address = (new PostalAddressValidator())->parse($draft->address ?? new AddressDraft(null, null, null, null, null, null, null, null, null, null), $addressCollector);
        $collector->merge($addressCollector->result(), 'address');
        if ($mobilePhone === null || $email === null || !$address instanceof PostalAddress) {
            return null;
        }

        return ContactDetails::of($mobilePhone, $email, $address);
    }
}
