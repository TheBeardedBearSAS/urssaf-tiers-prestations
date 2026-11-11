<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * What a batch use case did with one record.
 */
enum ItemOutcome: string
{
    /** Unknown record, record of another tenant, or record not returned by URSSAF. */
    case NotFound = 'not_found';

    /** Not pending any more (e.g. claimed by another worker): left untouched. */
    case NotClaimed = 'not_claimed';

    /** Accepted by URSSAF (methods 010 / 050). */
    case Accepted = 'accepted';

    /** Refused by URSSAF (business errors, final). */
    case Rejected = 'rejected';

    /** URSSAF refused the tenant credentials; see RequeueAfterInvalidCredentials. */
    case InvalidCredentials = 'invalid_credentials';

    /** Moved back to pending, to be submitted again. */
    case Requeued = 'requeued';

    /** Failed technically once more after the maximum number of attempts: left in error, to be handled manually. */
    case Exhausted = 'exhausted';

    /**
     * Submitted without a usable answer (or the invoice number is already known by URSSAF): left submitting, to be
     * reconciled (ReconcileStaleSubmissions).
     */
    case Unconfirmed = 'unconfirmed';

    /**
     * URSSAF may already know the record (outcome of the call unknown, customer already registered): left in error,
     * never retried automatically; to be checked and handled manually.
     */
    case ManualReview = 'manual_review';

    /** The URSSAF status changed. */
    case Changed = 'changed';

    /** The URSSAF status was confirmed. */
    case Unchanged = 'unchanged';

    /** URSSAF returned a status code unknown to this package version: left untouched. */
    case UnknownStatus = 'unknown_status';

    /** URSSAF returned a record that was not asked for. */
    case Ignored = 'ignored';

    /** URSSAF refused the query for this record (codes attached): left untouched. */
    case Failed = 'failed';

    /** A stale submission was found at URSSAF and marked as submitted. */
    case Reconciled = 'reconciled';
}
