import { Injectable, inject, signal } from '@angular/core';
import { OAuthService, AuthConfig } from 'angular-oauth2-oidc';
import { environment } from '../../../environments/environment';

/**
 * T037: OidcAuthService.
 *
 * Wraps angular-oauth2-oidc with a signal-based API. The access token is
 * attached by AuthInterceptor; refresh is silent and PKCE-enabled.
 */
@Injectable({ providedIn: 'root' })
export class OidcAuthService {
  private readonly oauth = inject(OAuthService);

  readonly authenticated = signal(false);
  readonly claims = signal<Record<string, unknown>>({});

  init(): void {
    const config: AuthConfig = {
      issuer: environment.oidc.issuer,
      redirectUri: window.location.origin,
      clientId: environment.oidc.clientId,
      responseType: 'code',
      scope: 'openid profile email',
      useSilentRefresh: true,
      showDebugInformation: !environment.production,
    };
    this.oauth.configure(config);
    this.oauth.setupAutomaticSilentRefresh();
    this.oauth.loadDiscoveryDocumentAndTryLogin().then(() => {
      this.authenticated.set(this.oauth.hasValidAccessToken());
      this.claims.set((this.oauth.getIdentityClaims() as Record<string, unknown>) ?? {});
    });
  }

  accessToken(): string | null {
    return this.oauth.getAccessToken();
  }

  login(): void {
    this.oauth.initCodeFlow();
  }

  logout(): void {
    this.oauth.logOut();
  }
}
