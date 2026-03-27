<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Disable transactions to prevent PostgreSQL abort errors.
     */
    public $withinTransaction = false;

    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'pgsql_embeddings';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('embeddings')) {
            // Enable pgvector extension
            DB::connection($this->connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');

            Schema::connection($this->connection)->create('embeddings', function (Blueprint $table) {
                $table->id();
                $table->string('clinic_id')->index();
                $table->string('table_name')->index();
                $table->unsignedBigInteger('record_id');
                $table->text('content');
                $table->timestamp('updated_at')->nullable();

                // Unique constraint: one embedding per record per clinic
                $table->unique(['clinic_id', 'table_name', 'record_id'], 'embeddings_clinic_table_record_unique');
            });

            // Add vector column (not supported by Blueprint)
            DB::connection($this->connection)->statement(
                'ALTER TABLE embeddings ADD COLUMN embedding vector(1536)'
            );

            // Create IVFFlat index for fast cosine similarity search
            // NOTE: This index requires at least some rows to exist.
            // For initial setup, we use a HNSW index instead which works on empty tables.
            DB::connection($this->connection)->statement(
                'CREATE INDEX embeddings_embedding_idx ON embeddings USING hnsw (embedding vector_cosine_ops)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('embeddings');
    }
};
