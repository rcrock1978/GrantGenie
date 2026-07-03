import { Injectable, computed, inject } from '@angular/core';
import { OidcAuthService } from './oidc-auth.service';

/**
 * T037: TenantContextService.
 *
 * Exposes the current tenant (account_id) as a signal. Derived from the
 * OIDC claims; falls back to the user's `account_id` claim if present.
 */
@Injectable({ providedIn: 'root' })
export class TenantContextService {
  private readonly auth = inject(OidcAuthService);

  readonly tenantId = computed<string | null>(() => {
    const claims = this.auth.claims();
    return (claims['account_id'] as string | undefined) ?? null;
  });
}
