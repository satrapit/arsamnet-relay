<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('topic_id')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->restrictOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('priority', 12)->default('normal');
            $table->longText('payload_ciphertext');
            $table->char('payload_hash', 64);
            $table->char('idempotency_key_hash', 64)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at');
            $table->foreign(['topic_id', 'client_id'])->references(['id', 'client_id'])->on('topics')->restrictOnDelete();
            $table->unique(['client_id', 'idempotency_key_hash'], 'messages_idempotency_unique');
            $table->unique(['id', 'client_id']);
            $table->index(['client_id', 'created_at']);
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->char('message_id', 26);
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('state', 24)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('next_attempt_at')->nullable();
            $table->char('lease_token', 36)->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->foreign(['message_id', 'client_id'])->references(['id', 'client_id'])->on('messages')->restrictOnDelete();
            $table->foreign(['target_id', 'client_id'])->references(['id', 'client_id'])->on('delivery_targets')->restrictOnDelete();
            $table->foreign(['subscription_id', 'client_id'])->references(['id', 'client_id'])->on('subscriptions')->restrictOnDelete();
            $table->unique(['message_id', 'target_id']);
            $table->unique(['id', 'client_id']);
            $table->index(['state', 'next_attempt_at']);
        });

        Schema::create('delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->char('delivery_id', 26);
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->restrictOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('outcome', 24);
            $table->string('provider_ref', 191)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->json('response_meta')->nullable();
            $table->dateTime('created_at');
            $table->foreign('delivery_id')->references('id')->on('deliveries')->restrictOnDelete();
            $table->unique(['delivery_id', 'attempt_no']);
            $table->index(['provider_account_id', 'provider_ref']);
        });

        Schema::create('callback_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->restrictOnDelete();
            $table->char('event_id_hmac', 64);
            $table->foreignId('delivery_attempt_id')->nullable()->constrained('delivery_attempts')->restrictOnDelete();
            $table->string('status', 24);
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->unique(['provider_account_id', 'event_id_hmac'], 'callback_receipts_event_unique');
        });
    }

    public function down(): void
    {
        foreach (['callback_receipts', 'delivery_attempts', 'deliveries', 'messages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
