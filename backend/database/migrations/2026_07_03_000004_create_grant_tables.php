<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ingestion_sources is global (not tenant-scoped) — one source per platform
        Schema::create('ingestion_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 64)->unique();
            $table->string('base_url', 2048);
            $table->jsonb('auth_config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestampTz('last_run_at')->nullable();
            $table->string('last_run_status', 16)->nullable();
        });

        Schema::create('ingestion_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_id');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->string('status', 16);
            $table->integer('grants_fetched')->default(0);
            $table->integer('grants_upserted')->default(0);
            $table->text('error_summary')->nullable();

            $table->index(['source_id', 'started_at']);
            $table->foreign('source_id')->references('id')->on('ingestion_sources')->onDelete('cascade');
        });

        // grants are global (shared corpus) but eligibility decisions are per-tenant
        Schema::create('grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_id');
            $table->string('source_grant_id', 128);
            $table->string('title', 512);
            $table->string('funder_name', 255);
            $table->string('funder_url', 2048)->nullable();
            $table->text('description');
            $table->decimal('amount_min', 14, 2)->nullable();
            $table->decimal('amount_max', 14, 2)->nullable();
            $table->timestampTz('deadline_at')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->jsonb('categories')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('ntee_codes')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('service_area_states')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('organization_types')->default(DB::raw("'[]'::jsonb"));
            $table->string('status', 16);
            $table->jsonb('eligibility_rules')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('ingested_at');
            $table->timestampTz('last_seen_at');

            $table->unique(['source_id', 'source_grant_id']);
            $table->index('deadline_at');
            $table->index(['status', 'deadline_at']);
            $table->foreign('source_id')->references('id')->on('ingestion_sources')->onDelete('restrict');
        });

        Schema::create('eligibility_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('grant_id');
            $table->string('result', 16); // eligible | not_eligible
            $table->jsonb('matched_rule_ids')->default(DB::raw("'[]'::jsonb"));
            $table->timestampTz('evaluated_at');
            $table->string('org_profile_hash', 64);

            $table->index(['account_id', 'grant_id']);
            $table->index(['grant_id', 'evaluated_at']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('grant_id')->references('id')->on('grants')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE eligibility_decisions ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY eligibility_decisions_tenant_isolation ON eligibility_decisions
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_decisions');
        Schema::dropIfExists('grants');
        Schema::dropIfExists('ingestion_runs');
        Schema::dropIfExists('ingestion_sources');
    }
};
