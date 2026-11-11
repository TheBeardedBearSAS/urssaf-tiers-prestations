<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

enum RejectionContext: string
{
    /** The provider must regularise the invoice. */
    case Regularisation = 'regularisation';

    /** The customer contested the request (status 40). */
    case Contestation = 'contestation';

    /** The request was cancelled (status 110). */
    case Cancellation = 'cancellation';
}
