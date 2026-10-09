<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What only agents have (RFC 0003, RFC 0011): scopes bounded by their
 * owner, approval rules, a space allow-list, and for a person's MCP
 * assistant, whether its spaces follow the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->jsonb('scopes')->default('[]');
            $table->jsonb('requires_approval_for')->default('[]');
            $table->jsonb('space_ids')->default('[]');
            $table->jsonb('model')->nullable();
            $table->boolean('assistant')->default(false);
            $table->boolean('spaces_follow_principal')->default(false);
            $table->index(['workspace_id', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'owner_id']);
            $table->dropColumn(['description', 'scopes', 'requires_approval_for', 'space_ids', 'model', 'assistant', 'spaces_follow_principal']);
        });
    }
};
