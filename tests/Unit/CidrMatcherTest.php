<?php

namespace Tests\Unit;

use App\Http\Middleware\AuthenticateClientKey;
use PHPUnit\Framework\TestCase;

class CidrMatcherTest extends TestCase
{
    public function test_matching_ipv4_and_ipv6_ranges(): void
    {
        $guard = new AuthenticateClientKey();
        $this->assertTrue($guard->matchesCidr('192.0.2.1', '192.0.2.0/24'));
        $this->assertFalse($guard->matchesCidr('192.0.3.1', '192.0.2.0/24'));
        $this->assertTrue($guard->matchesCidr('2001:db8::1', '2001:db8::/32'));
        $this->assertFalse($guard->matchesCidr('2001:db9::1', '2001:db8::/32'));
        $this->assertFalse($guard->matchesCidr('192.0.2.1', '192.0.2.0/999'));
        $this->assertFalse($guard->matchesCidr('192.0.2.1', 'not-a-cidr'));
        $this->assertFalse($guard->matchesCidr('192.0.2.1', '2001:db8::/32'));
    }
}
