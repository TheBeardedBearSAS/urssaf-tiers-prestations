<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * Records of one tenant by state. Every state is listed, in workflow order, with 0 when there is no record.
 */
final readonly class TenantOverview
{
    /**
     * @param array<string, int> $registrations   customer registrations by RegistrationState value
     * @param array<string, int> $paymentRequests payment requests by SubmissionState value
     */
    public function __construct(public string $tenantId, public array $registrations, public array $paymentRequests) {}

    /**
     * Records that wait for a manual action: registrations and payment requests in "error" or "invalid_credentials".
     */
    public function needingAttention(): int
    {
        return $this->registrations['error'] + $this->registrations['invalid_credentials']
            + $this->paymentRequests['error'] + $this->paymentRequests['invalid_credentials'];
    }

    /**
     * @return array{tenant_id: string, registrations: array<string, int>, payment_requests: array<string, int>, needing_attention: int}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'registrations' => $this->registrations,
            'payment_requests' => $this->paymentRequests,
            'needing_attention' => $this->needingAttention(),
        ];
    }
}
