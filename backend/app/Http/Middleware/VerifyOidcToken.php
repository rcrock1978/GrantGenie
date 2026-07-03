<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * T022: VerifyOidcToken middleware.
 *
 * Validates an OIDC RS256 JWT issued by Auth0 (or a compatible provider). On
 * success, attaches the verified claims to the request as attributes:
 *   - `oidc_sub`        : OIDC subject
 *   - `oidc_account_id` : tenant account_id claim
 *   - `oidc_roles`      : roles claim (array)
 *
 * The user record is then resolved by `account_id` + `oidc_subject` in
 * downstream auth logic. If no matching user exists, returns 401.
 *
 * Production note: the JWKS endpoint should be polled and the public key
 * rotated; for MVP we read the static public key from `config/auth.php`
 * (`oidc.public_key`). Migrations to JWKS are tracked as a follow-up.
 */
final class VerifyOidcToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->header('Authorization');
        if ($auth === null || ! str_starts_with($auth, 'Bearer ')) {
            return response()->problem('unauthorized', 'Missing bearer token.', 401);
        }

        $jwt = substr($auth, 7);
        try {
            $parser = new Parser(new JoseEncoder());
            $token = $parser->parse($jwt);
            if (! $token instanceof UnencryptedToken) {
                return response()->problem('unauthorized', 'Token is not an unencrypted JWS.', 401);
            }

            $publicKey = config('auth.oidc.public_key');
            if (! is_string($publicKey) || $publicKey === '') {
                return response()->problem('misconfigured', 'OIDC public key not configured.', 500);
            }

            $validator = new Validator();
            $validator->assert($token, new SignedWith(new Sha256(), InMemory::plainText($publicKey)));

            $issuer = (string) config('auth.oidc.issuer');
            $audience = (string) config('auth.oidc.audience');
            $validator->assert($token, new IssuedBy($issuer));
            $validator->assert($token, new PermittedFor($audience));
        } catch (\Throwable $e) {
            return response()->problem('unauthorized', 'Invalid token: ' . $e->getMessage(), 401);
        }

        $claims = $token->claims();
        $request->attributes->set('oidc_sub', (string) $claims->get('sub'));
        $request->attributes->set('oidc_account_id', (string) $claims->get('account_id'));
        $request->attributes->set('oidc_roles', (array) $claims->get('roles', []));

        return $next($request);
    }
}
