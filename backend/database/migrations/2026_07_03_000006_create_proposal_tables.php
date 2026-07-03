<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('grant_id');
            $table->string('title', 512);
            $table->string('status', 32); // drafting|ready_for_review|in_review|approved|submitted|archived
            $table->jsonb('funder_profile')->nullable();
            $table->string('ai_model_used', 64)->nullable();
            $table->integer('ai_total_input_tokens')->default(0);
            $table->integer('ai_total_output_tokens')->default(0);
            $table->integer('ai_total_cost_cents')->default(0);
            $table->decimal('eval_relevance', 4, 3)->nullable();
            $table->decimal('eval_faithfulness', 4, 3)->nullable();
            $table->boolean('eval_passed')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index('grant_id');
            $table->index('eval_passed');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('grant_id')->references('id')->on('grants')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('proposal_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('proposal_id');
            $table->uuid('account_id');
            $table->string('kind', 32); // summary|need_statement|activities|budget_narrative|impact
            $table->smallInteger('order_index');
            $table->text('content');
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['proposal_id', 'kind']);
            $table->index('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('proposal_id')->references('id')->on('proposals')->onDelete('cascade');
        });

        Schema::create('proposal_section_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('section_id');
            $table->uuid('account_id');
            $table->text('content');
            $table->string('content_hash', 64);
            $table->uuid('author_id');
            $table->uuid('edit_lock_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['section_id', 'created_at']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('proposal_sections')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('citations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('section_version_id');
            $table->uuid('chunk_id');
            $table->integer('quote_span_start');
            $table->integer('quote_span_end');
            $table->decimal('confidence', 4, 3);

            $table->index('section_version_id');
            $table->index('chunk_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('section_version_id')->references('id')->on('proposal_section_versions')->onDelete('cascade');
            $table->foreign('chunk_id')->references('id')->on('document_chunks')->onDelete('restrict');
        });

        Schema::create('review_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('section_id');
            $table->uuid('author_id');
            $table->text('body');
            $table->text('anchor_quote')->nullable();
            $table->integer('anchor_quote_start')->nullable();
            $table->integer('anchor_quote_end')->nullable();
            $table->string('status', 16)->default('open');
            $table->uuid('resolved_by')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['account_id', 'section_id']);
            $table->index('status');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('proposal_sections')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::create('edit_locks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('proposal_id');
            $table->uuid('holder_user_id');
            $table->timestampTz('acquired_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('released_at')->nullable();

            $table->index(['account_id', 'proposal_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('proposal_id')->references('id')->on('proposals')->onDelete('cascade');
            $table->foreign('holder_user_id')->references('id')->on('users')->onDelete('restrict');
        });

        // Postgres partial unique index: only one active lock per proposal
        DB::statement('CREATE UNIQUE INDEX edit_locks_active_unique ON edit_locks (proposal_id) WHERE released_at IS NULL');

        Schema::create('budget_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('proposal_id');
            $table->string('category', 128);
            $table->string('description', 512);
            $table->bigInteger('amount_cents');
            $table->text('narrative')->nullable();
            $table->string('funder_category', 128)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'proposal_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('proposal_id')->references('id')->on('proposals')->onDelete('cascade');
        });

        // RLS for tenant-scoped tables
        foreach (['proposals', 'proposal_sections', 'proposal_section_versions', 'citations', 'review_comments', 'edit_locks', 'budget_items'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_tenant_isolation ON {$table}
                USING (account_id::text = current_setting('app.current_tenant_id', true))
                WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('edit_locks');
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('citations');
        Schema::dropIfExists('proposal_section_versions');
        Schema::dropIfExists('proposal_sections');
        Schema::dropIfExists('proposals');
    }
};
