<?php

namespace App\Providers\Adapters;

use App\Contracts\Providers\OutboundProviderAdapter;
use App\DTO\OutboundProviderRequest;
use App\DTO\OutboundProviderResult;
use App\Enums\ProviderAttemptOutcome;
use App\Enums\ProviderCapability;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * An explicit no-network adapter for deterministic local/testing contract QA.
 *
 * It is not registered in the queue or routed to a production provider.
 * The live sender must not instantiate this adapter in production.
 */
final class FakeOutboundProviderAdapter implements OutboundProviderAdapter
{
    public function __construct(private readonly string $outboundChannel = 'email')
    {
        if (! in_array($outboundChannel, ['sms', 'email', 'webhook'], true)) {
            throw new InvalidArgumentException('Unsupported fake provider channel.');
        }
    }

    public function code(): string
    {
        return 'fake-only';
    }

    public function channel(): string
    {
        return $this->outboundChannel;
    }

    /** @return list<ProviderCapability> */
    public function capabilities(): array
    {
        return [ProviderCapability::Send, ProviderCapability::SignedDeliveryReceipt];
    }

    public function send(OutboundProviderRequest $request): OutboundProviderResult
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Fake outbound provider is prohibited outside local/testing.');
        }

        if ($request->channel() !== $this->outboundChannel) {
            throw new InvalidArgumentException('Outbound request channel does not match provider channel.');
        }

        // No HTTP/SMTP/SMS/webhook transport, I/O, logs or persistence.
        // Reference is synthetic and only for separate fake callback tests.
        return new OutboundProviderResult(
            ProviderAttemptOutcome::Accepted, 202, 'fake-'.(string) Str::ulid(),
        );
    }
}
