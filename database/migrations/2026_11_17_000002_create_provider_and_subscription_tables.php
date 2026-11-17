<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->string('adapter_code', 64);
            $table->string('channel', 20);
            $table->string('display_name', 100);
            $table->longText('credentials_ciphertext');
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['client_id', 'channel']);
        });

        Schema::create('client_provider_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('provider_account_id')->constrained('provider_accounts')->restrictOnDelete();
            $table->string('channel', 20);
            $table->unsignedSmallInteger('priority');
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->unique(['client_id', 'channel', 'priority'], 'routes_priority_unique');
            $table->unique(['client_id', 'channel', 'provider_account_id'], 'routes_provider_unique');
        });

        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('channel', 20);
            $table->text('subject_template_ciphertext')->nullable();
            $table->longText('body_template_ciphertext');
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->unique(['client_id', 'channel', 'code']);
        });

        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->unique(['client_id', 'name']);
            $table->unique(['id', 'client_id']);
        });

        Schema::create('delivery_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('channel', 20);
            $table->text('address_ciphertext');
            $table->char('address_hmac', 64);
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->unique(['client_id', 'channel', 'address_hmac'], 'targets_client_channel_hmac_unique');
            $table->unique(['id', 'client_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('target_id');
            $table->boolean('enabled')->default(true);
            $table->dateTime('created_at');
            $table->foreign(['topic_id', 'client_id'])->references(['id', 'client_id'])->on('topics')->restrictOnDelete();
            $table->foreign(['target_id', 'client_id'])->references(['id', 'client_id'])->on('delivery_targets')->restrictOnDelete();
            $table->unique(['client_id', 'topic_id', 'target_id'], 'subscriptions_unique');
            $table->unique(['id', 'client_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'subscriptions', 'delivery_targets', 'topics', 'templates',
            'client_provider_routes', 'provider_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
