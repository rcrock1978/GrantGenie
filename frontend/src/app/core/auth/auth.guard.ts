import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { OidcAuthService } from './oidc-auth.service';

/**
 * T037: authGuard.
 * Redirects unauthenticated users to /login.
 */
export const authGuard: CanActivateFn = () => {
  const auth = inject(OidcAuthService);
  const router = inject(Router);
  if (auth.authenticated()) {
    return true;
  }
  return router.parseUrl('/login');
};

/**
 * roleGuard: ensures the current user has at least one of the required roles.
 * Use: canActivate: [roleGuard(['admin', 'writer'])]
 */
export const roleGuard =
  (allowed: string[]): CanActivateFn =>
  () => {
    const auth = inject(OidcAuthService);
    const router = inject(Router);
    const claims = auth.claims();
    const roles = (claims['roles'] as string[] | undefined) ?? [];
    if (roles.some((r) => allowed.includes(r))) {
      return true;
    }
    return router.parseUrl('/forbidden');
  };
