<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The audit log (RFC 0003, ADR 0021). Append-only, so there is no
 * updated_at, and no foreign keys that could cascade a delete into it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('workspace_id', 30)->nullable();
            $table->string('action', 64);
            $table->string('outcome', 16);
            $table->string('surface', 16);
            $table->string('scope', 32)->nullable();
            $table->string('actor_id', 30)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('on_behalf_of_id', 30)->nullable();
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 30)->nullable();
            $table->jsonb('changes')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['workspace_id', 'actor_id', 'occurred_at']);
            $table->index(['workspace_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
