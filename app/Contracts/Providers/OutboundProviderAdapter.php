<?php

namespace App\Contracts\Providers;

use App\DTO\OutboundProviderRequest;
use App\DTO\OutboundProviderResult;
use App\Enums\ProviderCapability;

/**
 * Transport boundary only; NOT a queue worker, router or public endpoint.
 *
 * Implementations must not persist request bodies, credentials, addresses
 * or raw provider responses in logs or job payloads.
 */
interface OutboundProviderAdapter
{
    public function code(): string;

    public function channel(): string;

    /** @return list<ProviderCapability> */
    public function capabilities(): array;

    public function send(OutboundProviderRequest $request): OutboundProviderResult;
}
