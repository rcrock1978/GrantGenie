<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T018: TenantScope middleware.
 *
 * Sets the Postgres session variable `app.current_tenant_id` from the JWT
 * `account_id` claim on every request. Combined with RLS policies defined in
 * migrations, this enforces multi-tenant isolation at the database layer
 * (Constitution Principle IV: defense in depth).
 *
 * - Extracts `account_id` from the verified OIDC token attached by VerifyOidcToken.
 * - Asserts the authenticated user belongs to the same tenant.
 * - Calls SET LOCAL within any active DB transaction so the value is reset
 *   automatically at commit/rollback.
 */
final class TenantScope
{
    public function __construct(private readonly Connection $db) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->problem('unauthorized', 'Authentication required.', 401);
        }

        $tokenAccountId = $request->attributes->get('oidc_account_id');
        if ($tokenAccountId === null) {
            return response()->problem('unauthorized', 'Token missing tenant claim.', 401);
        }

        $userAccountId = $user->account_id ?? null;
        if ($userAccountId !== $tokenAccountId) {
            return response()->problem('forbidden', 'Token tenant does not match user tenant.', 403);
        }

        // Set Postgres session var for RLS. SET LOCAL is scoped to the current transaction.
        $this->db->statement("SET LOCAL app.current_tenant_id = ?", [$tokenAccountId]);

        return $next($request);
    }
}
