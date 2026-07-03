<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('org_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();
            $table->text('mission');
            $table->text('history')->nullable();
            $table->jsonb('programs')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('service_area_states')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('ntee_codes')->default(DB::raw("'[]'::jsonb"));
            $table->string('organization_type', 64);
            $table->bigInteger('annual_budget_cents')->nullable();
            $table->smallInteger('years_operating')->nullable();
            $table->string('contact_email', 320);
            $table->string('website_url', 2048)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        DB::statement('ALTER TABLE org_profiles ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY org_profiles_tenant_isolation ON org_profiles
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('org_profiles');
    }
};
