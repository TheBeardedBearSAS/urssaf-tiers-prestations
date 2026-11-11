<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\PaymentRequestView;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;

/**
 * Returns the views in the order of the query (invoice numbers first, then ids); unknown records and records of
 * another tenant are left out.
 */
final readonly class FindPaymentRequestsHandler
{
    public function __construct(private PaymentRequestRepository $paymentRequests) {}

    /**
     * @return list<PaymentRequestView>
     */
    public function __invoke(FindPaymentRequests $query): array
    {
        $found = [
            ...array_map(
                fn(InvoiceNumber $number): ?PaymentRequest => $this->paymentRequests->findByInvoiceNumber($query->tenantId, $number),
                $query->invoiceNumbers,
            ),
            ...array_map($this->find(...), $query->paymentRequestIds),
        ];
        $views = [];
        foreach ($found as $request) {
            if (!$request instanceof PaymentRequest || !$request->tenantId()->equals($query->tenantId)) {
                continue;
            }
            $views[] = PaymentRequestView::fromPaymentRequest($request);
        }

        return $views;
    }

    private function find(LocalPaymentRequestId $id): ?PaymentRequest
    {
        try {
            return $this->paymentRequests->get($id);
        } catch (RecordNotFound) {
            return null;
        }
    }
}
