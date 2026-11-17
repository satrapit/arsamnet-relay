<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->longText('value');
            $table->unsignedBigInteger('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->string('owner', 191);
            $table->unsignedBigInteger('expiration');
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue', 191);
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedBigInteger('reserved_at')->nullable();
            $table->unsignedBigInteger('available_at');
            $table->unsignedBigInteger('created_at');
            $table->index(['queue', 'reserved_at', 'available_at']);
        });
        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id', 191)->primary();
            $table->string('name', 191);
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->text('options')->nullable();
            $table->unsignedBigInteger('cancelled_at')->nullable();
            $table->unsignedBigInteger('created_at');
            $table->unsignedBigInteger('finished_at')->nullable();
        });
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 191)->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->dateTime('failed_at')->useCurrent();
        });
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191);
            $table->string('scope', 191);
            $table->text('value');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->unique(['name', 'scope']);
        });
    }

    public function down(): void
    {
        foreach (['features', 'failed_jobs', 'job_batches', 'jobs', 'cache_locks', 'cache'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
