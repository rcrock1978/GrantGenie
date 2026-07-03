<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('slug', 64)->unique();
            $table->string('plan', 16)->default('free');
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->smallInteger('id')->primary();
            $table->string('name', 32)->unique();
            $table->string('guard_name', 32)->default('api');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('email', 320)->unique();
            $table->string('display_name', 255);
            $table->string('oidc_subject', 255);
            $table->string('status', 16)->default('active');
            $table->timestampTz('last_login_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->unique(['oidc_subject', 'account_id']);
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->smallInteger('role_id');
            $table->uuid('account_id');
            $table->timestampTz('granted_at')->useCurrent();
            $table->uuid('granted_by')->nullable();

            $table->primary(['user_id', 'role_id', 'account_id']);
            $table->index('account_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        // RLS for users
        DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY users_tenant_isolation ON users
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");

        // RLS for user_roles
        DB::statement('ALTER TABLE user_roles ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY user_roles_tenant_isolation ON user_roles
            USING (account_id::text = current_setting('app.current_tenant_id', true))
            WITH CHECK (account_id::text = current_setting('app.current_tenant_id', true))
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('accounts');
    }
};
