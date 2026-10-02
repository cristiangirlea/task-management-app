<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor authentication with an authenticator app (TOTP, RFC 6238).
 *
 * Setting it up is two steps: begin() stores a new secret, and confirm()
 * puts it in force once the user proves their app has it. Signing in is two
 * steps too: a correct password returns a short-lived challenge instead of
 * a token, and a code (or a recovery code) exchanges it for the token.
 */
class TwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    public const CHALLENGE_TTL_SECONDS = 300;

    public const CHALLENGE_MAX_ATTEMPTS = 5;

    public function __construct(protected Google2FA $google2fa) {}

    /**
     * A new secret, not yet in force. Starting again replaces an unconfirmed one.
     *
     * @return array{secret: string, otpauth_url: string, qr_code: string}
     */
    public function begin(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();

        $url = $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $secret);

        return ['secret' => $secret, 'otpauth_url' => $url, 'qr_code' => $this->qrCode($url)];
    }

    /**
     * Puts the pending secret in force if the code matches it.
     *
     * @return list<string>|null the recovery codes, shown this once; null for a wrong code
     */
    public function confirm(User $user, string $code): ?array
    {
        if (! $this->verifyCode($user, $code)) {
            return null;
        }

        $user->two_factor_confirmed_at = now();

        return $this->regenerateRecoveryCodes($user);
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestep' => null,
        ])->save();
    }

    /**
     * New recovery codes replace the old ones. Only their hashes are stored.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5));
        }

        $user->two_factor_recovery_codes = array_map(fn (string $code) => $this->hashRecoveryCode($code), $codes);
        $user->save();

        return $codes;
    }

    /**
     * A code from the app, accepted once: a code already used, or an older
     * one, is refused, so a code seen over someone's shoulder is worthless.
     */
    public function verifyCode(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if ($locked?->two_factor_secret === null) {
                return false;
            }

            // One step either side of now, for clock drift. Passing the last
            // step (0 before any) makes this return the step that matched.
            $step = $this->google2fa->verifyKeyNewer($locked->two_factor_secret, $code, $locked->two_factor_last_timestep ?? 0, 1);
            if ($step === false) {
                return false;
            }

            $locked->two_factor_last_timestep = $step;
            $locked->save();
            $user->two_factor_last_timestep = $step;

            return true;
        });
    }

    /**
     * A recovery code works once and is then gone.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hashRecoveryCode($code);

        return DB::transaction(function () use ($user, $hash): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $remaining = $locked?->two_factor_recovery_codes ?? [];

            $match = null;
            foreach ($remaining as $index => $stored) {
                if (hash_equals($stored, $hash)) {
                    $match = $index;
                }
            }
            if ($match === null) {
                return false;
            }

            unset($remaining[$match]);
            $locked->two_factor_recovery_codes = array_values($remaining);
            $locked->save();
            $user->two_factor_recovery_codes = $locked->two_factor_recovery_codes;

            return true;
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * After a correct password: the token the second step must present.
     */
    public function createChallenge(User $user): string
    {
        $token = Str::random(64);

        Cache::put($this->challengeKey($token), [
            'user_id' => $user->id,
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::CHALLENGE_TTL_SECONDS)->getTimestamp(),
        ], self::CHALLENGE_TTL_SECONDS);

        return $token;
    }

    /**
     * The user a live challenge belongs to; null once it expired, was used
     * or ran out of attempts.
     */
    public function challengeUser(string $token): ?User
    {
        $challenge = Cache::get($this->challengeKey($token));

        return is_array($challenge) ? User::find($challenge['user_id']) : null;
    }

    /**
     * Counts a wrong code. True while the challenge may still be answered.
     */
    public function failChallenge(string $token): bool
    {
        $key = $this->challengeKey($token);
        $challenge = Cache::get($key);
        if (! is_array($challenge)) {
            return false;
        }

        $challenge['attempts']++;
        $ttl = $challenge['expires_at'] - now()->getTimestamp();
        if ($challenge['attempts'] >= self::CHALLENGE_MAX_ATTEMPTS || $ttl <= 0) {
            Cache::forget($key);

            return false;
        }

        Cache::put($key, $challenge, $ttl);

        return true;
    }

    public function finishChallenge(string $token): void
    {
        Cache::forget($this->challengeKey($token));
    }

    private function challengeKey(string $token): string
    {
        return 'two-factor-challenge:'.hash('sha256', $token);
    }

    /**
     * Case, spaces and the dash do not matter when typing a recovery code.
     */
    private function hashRecoveryCode(string $code): string
    {
        return hash('sha256', preg_replace('/[^a-z0-9]/', '', Str::lower($code)));
    }

    private function qrCode(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
