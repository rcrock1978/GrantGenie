<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('proposal_id')->unique();
            $table->string('funder_name', 255);
            $table->timestampTz('submitted_at');
            $table->string('funder_confirmation_id', 128)->nullable();
            $table->string('status', 32); // submitted|under_review|awarded|declined|withdrawn
            $table->bigInteger('awarded_amount_cents')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['account_id', 'status']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('proposal_id')->references('id')->on('proposals')->onDelete('cascade');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('user_id');
            $table->string('kind', 32); // deadline_reminder|review_request|comment_reply|system
            $table->string('title', 255);
            $table->text('body');
            $table->string('link_url', 2048)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
            $table->index(['account_id', 'created_at']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('deadline_reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('grant_id');
            $table->uuid('user_id');
            $table->smallInteger('days_before');
            $table->timestampTz('sent_at')->useCurrent();
            $table->uuid('notification_id')->nullable();

            $table->unique(['account_id', 'grant_id', 'user_id', 'days_before'], 'deadline_reminders_unique');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('grant_id')->references('id')->on('grants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('notification_id')->references('id')->on('notifications')->onDelete('set null');
        });

        // audit_logs: append-only via trigger (per data-model.md: blocks UPDATE/DELETE)
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('account_id');
            $table->uuid('actor_user_id')->nullable();
            $table->string('action', 64);
            $table->string('resource_type', 64);
            $table->uuid('resource_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('correlation_id', 64);
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['account_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('correlation_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Immutability trigger: any UPDATE or DELETE on audit_logs raises an exception
        DB::statement("
            CREATE OR REPLACE FUNCTION audit_logs_immutable()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only; % is not allowed', TG_OP
                    USING ERRCODE = 'check_violation';
            END;
            $$ LANGUAGE plpgsql
        ");
        DB::statement('
            CREATE TRIGGER audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()
        ');
        DB::statement('
            CREATE TRIGGER audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW EXECUTE FUNCTION audit_logs_immutable()
        ');

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->string('event_type', 64);
            $table->string('event_version', 8)->default(1);
            $table->jsonb('payload');
            $table->string('status', 16)->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['status', 'next_attempt_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('key', 128);
            $table->string('endpoint', 255);
            $table->string('request_hash', 64);
            $table->integer('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('expires_at');

            $table->unique(['account_id', 'key']);
            $table->index('expires_at');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        // RLS for tenant-scoped tables (audit_logs + outbox_messages + idempotency_keys too)
        foreach (['submissions', 'notifications', 'deadline_reminders', 'audit_logs', 'outbox_messages', 'idempotency_keys'] as $table) {
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
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('outbox_messages');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS audit_logs_immutable()');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('deadline_reminders');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('submissions');
    }
};
