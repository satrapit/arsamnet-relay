<?php

namespace App\DTO;

use InvalidArgumentException;
use LogicException;

/**
 * A short-lived, in-memory transport request, never a Laravel queue payload.
 *
 * Sensitive destination/content intentionally have private visibility.
 * Do not log, serialize or persist this object. Providers may read values
 * only when actually performing an authorized transport operation.
 */
final readonly class OutboundProviderRequest
{
    public function __construct(
        private string $deliveryId,
        private string $channel,
        private string $destination,
        private string $content,
    ) {
        if (! preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $deliveryId)) {
            throw new InvalidArgumentException('Delivery ID must be a ULID.');
        }

        if (! in_array($channel, ['sms', 'email', 'webhook'], true)) {
            throw new InvalidArgumentException('Unsupported outbound channel.');
        }

        if ($destination === '' || strlen($destination) > 2048
            || $content === '' || strlen($content) > 16384) {
            throw new InvalidArgumentException('Destination or content exceeds request limits.');
        }
    }

    public function deliveryId(): string
    {
        return $this->deliveryId;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function destination(): string
    {
        return $this->destination;
    }

    public function content(): string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return [
            'delivery_id' => $this->deliveryId,
            'channel' => $this->channel,
            'destination' => '[REDACTED]',
            'content' => '[REDACTED]',
        ];
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new LogicException('Sensitive outbound requests must never be serialized.');
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new LogicException('Sensitive outbound requests must never be unserialized.');
    }
}
