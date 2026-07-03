<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api by the bootstrap config. The v1 group
| applies OIDC auth (VerifyOidcToken), tenant scoping (TenantScope), and
| idempotency (IdempotencyKey) for mutating verbs. Specific endpoint wiring
| is added per user story in later phases.
|
*/

Route::middleware(['oidc', 'tenant'])->prefix('v1')->group(function (): void {
    // Auth
    Route::get('/auth/me', fn (Request $r) => $r->user());

    // Health
    Route::get('/healthz', fn () => response()->json(['status' => 'ok']));
});

// Mutating v1 endpoints: add the `idempotent` middleware
Route::middleware(['oidc', 'tenant', 'idempotent'])->prefix('v1')->group(function (): void {
    // Per-user-story routes wired in Phase 3+
});
