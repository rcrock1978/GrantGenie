import { inject } from '@angular/core';
import { HttpEvent, HttpHandlerFn, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { Observable } from 'rxjs';
import { OidcAuthService } from './oidc-auth.service';

/**
 * T037: AuthInterceptor.
 *
 * Attaches the OIDC access token (Bearer) and the per-request correlation id
 * to every outbound HTTP call. Skips non-API requests (assets, etc.).
 */
export const authInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
): Observable<HttpEvent<unknown>> => {
  const auth = inject(OidcAuthService);
  const token = auth.accessToken();
  if (!token || !req.url.includes('/api/')) {
    return next(req);
  }

  const correlationId = crypto.randomUUID();
  const authed = req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`,
      'X-Correlation-Id': correlationId,
      'Idempotency-Key': req.method === 'GET' ? '' : crypto.randomUUID(),
    },
  });
  return next(authed);
};
