<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | OIDC Settings (Auth0 or compatible)
    |--------------------------------------------------------------------------
    |
    | RS256-signed JWTs are validated by App\Http\Middleware\VerifyOidcToken.
    | The public key is read from `oidc.public_key` (single-line PEM). For
    | production, replace with a JWKS-backed resolver that polls the
    | `oidc.jwks_uri` endpoint and rotates keys.
    |
    */

    'oidc' => [
        'issuer' => env('OIDC_ISSUER', 'https://auth.grantgenie.example/'),
        'audience' => env('OIDC_AUDIENCE', 'grantgenie-api'),
        'public_key' => env('OIDC_PUBLIC_KEY', ''),
        'jwks_uri' => env('OIDC_JWKS_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'discovery_search' => 60, // requests per minute per user
        'proposal_draft' => 10,  // requests per minute per user (AI is expensive)
        'document_upload' => 30, // requests per minute per tenant
    ],
];
