<?php

namespace App\Enums;

/**
 * Declared adapter capabilities; a provider cannot claim delivery proof
 * merely because an outbound send was accepted.
 */
enum ProviderCapability: string
{
    case Send = 'send';
    case SignedDeliveryReceipt = 'signed_delivery_receipt';
    case StatusQuery = 'status_query';
}
