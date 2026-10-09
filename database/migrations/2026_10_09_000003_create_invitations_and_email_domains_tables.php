<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Invitations and joining by email domain (RFC 0003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('workspace_id', 30);
            $table->string('email');
            $table->string('role', 16);
            $table->string('status', 16);
            $table->string('token_hash', 64)->unique();
            $table->jsonb('space_ids')->default('[]');
            $table->string('invited_by_id', 30);
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('invited_by_id')->references('id')->on('members');
            $table->index(['workspace_id', 'status', 'email']);
        });

        Schema::create('workspace_email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 30);
            $table->string('domain');
            $table->string('added_by_id', 30);
            $table->timestamp('created_at')->nullable();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('added_by_id')->references('id')->on('members');
            $table->unique(['workspace_id', 'domain']);
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_email_domains');
        Schema::dropIfExists('invitations');
    }
};
