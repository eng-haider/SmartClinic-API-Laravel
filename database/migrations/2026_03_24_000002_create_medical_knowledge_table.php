<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        if (!Schema::connection($this->connection)->hasTable('medical_knowledge')) {
            Schema::connection($this->connection)->create('medical_knowledge', function (Blueprint $table) {
                $table->id();
                $table->string('clinic_id')->nullable()->index();
                $table->string('title');
                $table->text('content');
                $table->timestamps();
            });

            // Add vector column (not supported by Blueprint)
            DB::connection($this->connection)->statement(
                'ALTER TABLE medical_knowledge ADD COLUMN embedding vector(1536)'
            );

            // Create HNSW index for fast cosine similarity search
            DB::connection($this->connection)->statement(
                'CREATE INDEX medical_knowledge_embedding_idx ON medical_knowledge USING hnsw (embedding vector_cosine_ops)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('medical_knowledge');
    }
};
