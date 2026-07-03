<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('boilerplate_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('title', 255);
            $table->string('original_filename', 512);
            $table->string('format', 8); // pdf|docx|txt|md
            $table->bigInteger('size_bytes');
            $table->string('storage_key', 1024);
            $table->uuid('uploaded_by');
            $table->string('status', 16)->default('uploaded'); // uploaded|processing|ready|failed
            $table->integer('chunk_count')->default(0);
            $table->text('processing_error')->nullable();
            $table->timestampTz('uploaded_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            $table->index(['account_id', 'uploaded_at']);
            $table->index('status');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('document_id');
            $table->integer('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64);
            $table->integer('page_number')->nullable();
            $table->integer('char_offset_start');
            $table->integer('char_offset_end');
            $table->string('embedding_model', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['document_id', 'chunk_index']);
            $table->index('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('document_id')->references('id')->on('boilerplate_documents')->onDelete('cascade');
        });

        // HNSW index for cosine distance (constitution: pgvector, m=16, ef_construction=64)
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX document_chunks_embedding_hnsw ON document_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)');

        DB::statement('ALTER TABLE boilerplate_documents ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY boilerplate_documents_tenant_isolation ON boilerplate_documents
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");

        DB::statement('ALTER TABLE document_chunks ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY document_chunks_tenant_isolation ON document_chunks
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('boilerplate_documents');
    }
};
