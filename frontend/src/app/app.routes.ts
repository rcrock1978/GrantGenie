import { Routes } from '@angular/router';
import { authGuard } from './core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./features/auth/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./core/shell/app-shell.component').then((m) => m.AppShell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'discovery' },
      {
        path: 'discovery',
        loadComponent: () =>
          import('./features/discovery/discovery-page.component').then(
            (m) => m.DiscoveryPageComponent,
          ),
      },
      {
        path: 'org-profile',
        loadComponent: () =>
          import('./features/org-profile/org-profile-page.component').then(
            (m) => m.OrgProfilePageComponent,
          ),
      },
      {
        path: 'boilerplate',
        loadComponent: () =>
          import('./features/boilerplate/boilerplate-page.component').then(
            (m) => m.BoilerplatePageComponent,
          ),
      },
      {
        path: 'proposals',
        loadComponent: () =>
          import('./features/proposals/proposals-page.component').then(
            (m) => m.ProposalsPageComponent,
          ),
      },
      {
        path: 'proposals/:id',
        loadComponent: () =>
          import('./features/proposals/proposal-detail-page.component').then(
            (m) => m.ProposalDetailPageComponent,
          ),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
