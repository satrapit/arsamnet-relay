<?php

namespace Tests\Feature;

use App\Support\SecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityAuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('relay.audit_hmac_key', bin2hex(random_bytes(32)));
    }

    public function test_record_is_verified_and_detects_modified_severity(): void
    {
        SecurityAudit::record('feature_flag.changed', 'high', null, [
            'flag_key' => 'webhook-delivery',
            'new_state' => true,
        ], null, '192.0.2.10');

        $row = DB::table('security_events')->first();
        $this->assertNotNull($row);
        $this->assertTrue(SecurityAudit::verifyRecord($row));
        $this->assertSame(64, strlen($row->source_ip_hmac));
        $this->assertStringNotContainsString('192.0.2.10', $row->context_json);
        DB::table('security_events')->where('id', $row->id)->update(['severity' => 'critical']);
        $this->assertFalse(SecurityAudit::verifyRecord(DB::table('security_events')->first()));
    }

    public function test_unallowlisted_metadata_does_not_enter_security_log(): void
    {
        SecurityAudit::record('auth.login.failed', 'warning', null, [
            'password' => 'very-private',
            'otp' => '123456',
        ]);

        $event = DB::table('security_events')->first();
        $this->assertSame('{}', $event->context_json);
        $this->assertTrue(SecurityAudit::verifyRecord($event));
    }
}
