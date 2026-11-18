<?php

namespace App\Enums;

/**
 * Internal normalized outcomes, not proof of end-recipient delivery.
 * There is deliberately no "delivered" case: delivery needs verified proof.
 */
enum ProviderAttemptOutcome: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Retryable = 'retryable';
    case Ambiguous = 'ambiguous';

    public function deliveryState(): string
    {
        return match ($this) {
            self::Accepted => 'accepted',
            self::Rejected => 'rejected',
            self::Retryable, self::Ambiguous => 'review_required',
        };
    }

    public function safeErrorCode(): ?string
    {
        return match ($this) {
            self::Accepted => null,
            self::Rejected => 'PROVIDER_REJECTED',
            self::Retryable => 'PROVIDER_RETRYABLE',
            self::Ambiguous => 'PROVIDER_AMBIGUOUS',
        };
    }
}
