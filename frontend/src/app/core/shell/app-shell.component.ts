import { Component, inject, OnInit } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { OidcAuthService } from '../auth/oidc-auth.service';

/**
 * T037: AppShell.
 *
 * Top-level layout. Renders the nav shell with role-aware menu items.
 * Initialises the OIDC service on first mount.
 */
@Component({
  selector: 'gg-app-shell',
  standalone: true,
  imports: [RouterOutlet],
  template: `
    <header class="app-header">
      <a routerLink="/discovery" class="brand">GrantGenie</a>
      <nav>
        <a routerLink="/discovery">Discover</a>
        <a routerLink="/proposals">Proposals</a>
        <a routerLink="/boilerplate">Library</a>
        <a routerLink="/org-profile">Profile</a>
      </nav>
    </header>
    <main class="app-main">
      <router-outlet />
    </main>
  `,
  styles: [
    `
      .app-header {
        display: flex;
        gap: 1.5rem;
        align-items: center;
        padding: 0.75rem 1.25rem;
        background: #f7f7f9;
        border-bottom: 1px solid #e5e7eb;
      }
      .app-main {
        padding: 1.25rem;
      }
    `,
  ],
})
export class AppShell implements OnInit {
  private readonly auth = inject(OidcAuthService);
  ngOnInit(): void {
    this.auth.init();
  }
}
