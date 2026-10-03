<?php

namespace Tests\Concerns;

/**
 * Passport signs access tokens with an RSA key pair. Tests make one per
 * process and hand it over through config, as production does through env.
 */
trait UsesOAuthKeys
{
    /** @var array{private: string, public: string}|null */
    private static ?array $oauthKeys = null;

    protected function setUpOAuthKeys(): void
    {
        if (self::$oauthKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$oauthKeys = ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
        }

        config(['passport.private_key' => self::$oauthKeys['private'], 'passport.public_key' => self::$oauthKeys['public']]);
    }
}
