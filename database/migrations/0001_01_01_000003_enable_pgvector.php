<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Search's embeddings live in pgvector (ADR 0045), so the extension is
 * part of the database from the first migration. The Sail and CI images
 * are pgvector/pgvector, which ship it (ADR 0069).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
    }

    public function down(): void
    {
        //
    }
};
