<?php

namespace App\Services\OAuth;

use Laravel\Passport\Passport;

/**
 * Passport signs and checks OAuth tokens with an RSA key pair, from
 * PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY or storage/oauth-*.key
 * (`php artisan passport:keys`). Without one, OAuth is off and the MCP
 * server takes Sanctum tokens only.
 */
class OAuthKeys
{
    public static function present(): bool
    {
        if (filled(config('passport.private_key')) && filled(config('passport.public_key'))) {
            return true;
        }

        return is_file(Passport::keyPath('oauth-private.key')) && is_file(Passport::keyPath('oauth-public.key'));
    }
}
