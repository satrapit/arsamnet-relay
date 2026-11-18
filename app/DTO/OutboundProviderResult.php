<?php

namespace App\DTO;

use App\Enums\ProviderAttemptOutcome;
use InvalidArgumentException;

/**
 * Sanitized transport classification. No provider payload, recipient,
 * OTP, response body or credentials are stored here.
 */
final readonly class OutboundProviderResult
{
    public function __construct(
        public ProviderAttemptOutcome $outcome,
        public ?int $httpStatus = null,
        public ?string $providerReference = null,
    ) {
        if ($httpStatus !== null && ($httpStatus < 100 || $httpStatus > 599)) {
            throw new InvalidArgumentException('HTTP status must be 100–599 or null.');
        }

        if ($providerReference !== null && ($outcome !== ProviderAttemptOutcome::Accepted
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $providerReference))) {
            throw new InvalidArgumentException('Only accepted results may carry an opaque provider reference.');
        }
    }

    /**
     * An accepted result is not a delivered receipt; that requires an
     * authenticated provider-specific callback/status-check mechanism.
     */
    public function deliveryState(): string
    {
        return $this->outcome->deliveryState();
    }
}
