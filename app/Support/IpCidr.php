<?php

namespace App\Support;

/**
 * A strict, dependency-free IPv4 / IPv6 network parser. Stored CIDRs are
 * canonicalized so equivalent network values have one database identity.
 */
final class IpCidr
{
    public static function normalize(string $cidr): ?string
    {
        $parts = explode('/', trim($cidr));
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || ! ctype_digit($parts[1])) {
            return null;
        }

        $address = @inet_pton($parts[0]);
        if ($address === false) {
            return null;
        }

        $maxBits = strlen($address) * 8;
        $prefix = (int) $parts[1];
        // A /0 rule is effectively unrestricted; use an empty allowlist instead.
        if ($prefix < 1 || $prefix > $maxBits) {
            return null;
        }

        $fullBytes = intdiv($prefix, 8);
        $partialBits = $prefix % 8;
        for ($index = $fullBytes; $index < strlen($address); $index++) {
            $address[$index] = chr(
                $index === $fullBytes && $partialBits > 0
                    ? ord($address[$index]) & ((0xFF << (8 - $partialBits)) & 0xFF)
                    : 0
            );
        }

        return inet_ntop($address).'/'.$prefix;
    }

    public static function contains(string $ip, string $cidr): bool
    {
        $normalized = self::normalize($cidr);
        if ($normalized === null) {
            return false;
        }

        [$networkIp, $prefixText] = explode('/', $normalized, 2);
        $remote = @inet_pton($ip);
        $network = @inet_pton($networkIp);
        if ($remote === false || $network === false || strlen($remote) !== strlen($network)) {
            return false;
        }

        $prefix = (int) $prefixText;
        $fullBytes = intdiv($prefix, 8);
        if (substr($remote, 0, $fullBytes) !== substr($network, 0, $fullBytes)) {
            return false;
        }

        $partialBits = $prefix % 8;
        if ($partialBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $partialBits)) & 0xFF;

        return (ord($remote[$fullBytes]) & $mask) === (ord($network[$fullBytes]) & $mask);
    }
}
