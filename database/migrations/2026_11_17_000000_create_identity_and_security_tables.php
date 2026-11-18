<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('email', 191)->unique();
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password', 255)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->rememberToken();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 191)->primary();
            $table->string('token', 255);
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48);
            $table->string('scope', 12);
            $table->dateTime('created_at');
            $table->unique(['scope', 'code']);
        });

        Schema::create('platform_role_assignments', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 191);
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('monthly_quota')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('client_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->dateTime('created_at');
            $table->unique(['client_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('label', 100);
            $table->string('key_prefix', 16);
            $table->char('key_hash', 64)->unique();
            $table->json('scopes');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['client_id', 'revoked_at']);
        });

        Schema::create('client_ip_allowlist', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('cidr', 49);
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->unique(['client_id', 'cidr']);
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('event_code', 80);
            $table->string('severity', 12);
            $table->char('source_ip_hmac', 64)->nullable();
            $table->json('context_json');
            $table->char('record_hmac', 64);
            $table->dateTime('created_at');
            $table->index(['event_code', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('operation_heartbeats', function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->dateTime('last_success_at')->nullable();
            $table->dateTime('last_failure_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->dateTime('updated_at');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id', 191)->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('user_id');
            $table->index('last_activity');
        });
    }

    public function down(): void
    {
        foreach ([
            'sessions', 'operation_heartbeats', 'security_events', 'client_ip_allowlist',
            'api_keys', 'client_memberships', 'clients', 'platform_role_assignments',
            'roles', 'password_reset_tokens', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
