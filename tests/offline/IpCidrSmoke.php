<?php

require dirname(__DIR__, 2).'/app/Support/IpCidr.php';

use App\Support\IpCidr;

$cases = [
    ['192.0.2.21/24', '192.0.2.0/24'],
    ['10.0.0.0/8', '10.0.0.0/8'],
    ['10.4.13.7/32', '10.4.13.7/32'],
    ['2001:db8:abcd::42/48', '2001:db8:abcd::/48'],
    ['2001:db8:ffff::1/128', '2001:db8:ffff::1/128'],
    ['::1/128', '::1/128'],
    ['127.0.0.1/0', null],
    ['2001:db8::/0', null],
    ['10.4.8.7/33', null],
    ['2001:db8::/129', null],
    ['1.2.3.4/garbage', null],
    ['1.2.3.4/', null],
    ['1.2.3.4', null],
    ['a.b.c.d/24', null],
];
$assertions = 0;
foreach ($cases as [$input, $expected]) {
    if (IpCidr::normalize($input) !== $expected) {
        throw new RuntimeException("Incorrect normalization: {$input}");
    }
    $assertions++;
}

$matches = [
    ['192.0.2.7', '192.0.2.0/24', true],
    ['192.0.3.7', '192.0.2.0/24', false],
    ['2001:db8:abcd::f', '2001:db8:abcd::42/48', true],
    ['2001:db8:abce::f', '2001:db8:abcd::/48', false],
    ['192.0.2.1', '2001:db8::/32', false],
    ['2001:db8::1', '192.0.2.0/24', false],
    ['192.0.2.1', 'invalid', false],
    ['127.0.0.1', '127.0.0.1/0', false],
    ['10.4.13.7', '10.4.13.7/32', true],
];
foreach ($matches as [$ip, $range, $expected]) {
    if (IpCidr::contains($ip, $range) !== $expected) {
        throw new RuntimeException("Incorrect matching: {$ip} vs {$range}");
    }
    $assertions++;
}

echo "PASS: {$assertions} offline IP CIDR checks (no Composer / database needed)\n";
