<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Identity's workspaces and members (RFC 0003). Keys are prefixed ULIDs
 * stored as text (ADR 0068). Accounts are the starter kit's `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('name');
            $table->string('handle', 32)->unique();
            $table->string('default_timezone');
            $table->unsignedSmallInteger('max_agents_per_member')->default(5);
            $table->unsignedSmallInteger('max_assistants_per_member')->default(5);
            $table->unsignedSmallInteger('brief_daily_limit')->default(30);
            $table->boolean('semantic_search')->default(true);
            $table->boolean('subscription_approval')->default(true);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('workspace_id', 30);
            $table->foreignId('account_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16);
            $table->string('display_name');
            $table->string('handle', 32);
            $table->string('role', 16)->nullable();
            $table->string('status', 16);
            $table->string('timezone');
            $table->string('owner_id', 30)->nullable();
            $table->string('acts_on_behalf_of_id', 30)->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'handle']);
            $table->unique(['workspace_id', 'account_id']);
            $table->index(['workspace_id', 'kind', 'status']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('members')->nullOnDelete();
            $table->foreign('acts_on_behalf_of_id')->references('id')->on('members')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
        Schema::dropIfExists('workspaces');
    }
};
