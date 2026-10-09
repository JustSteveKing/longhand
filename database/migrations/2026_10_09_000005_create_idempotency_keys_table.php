<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Idempotency keys on every POST (RFC 0002, ADR 0010), kept 24 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64);
            $table->string('method', 8);
            $table->string('path');
            $table->string('key');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status')->nullable();
            $table->jsonb('headers')->nullable();
            $table->text('body')->nullable();
            $table->timestampTz('created_at');

            $table->unique(['scope', 'method', 'path', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
