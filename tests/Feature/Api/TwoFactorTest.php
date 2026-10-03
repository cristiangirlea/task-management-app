<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->google2fa = app(Google2FA::class);
    }

    /** The code the app shows `$steps` time steps (30 s each) from now. */
    private function code(string $secret, int $steps = 0): string
    {
        return $this->google2fa->oathTotp($secret, $this->google2fa->getTimestamp() + $steps);
    }

    /** @return array{0: User, 1: string, 2: list<string>} user, secret and recovery codes */
    private function enabledUser(): array
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        Sanctum::actingAs($user);

        $secret = $this->postJson('/api/user/two-factor', ['password' => 'password'])->assertOk()->json('data.secret');
        $codes = $this->postJson('/api/user/two-factor/confirm', ['code' => $this->code($secret)])->assertOk()->json('data.recovery_codes');

        $this->app['auth']->forgetGuards();

        return [$user->refresh(), $secret, $codes];
    }

    private function challenge(): string
    {
        return $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor', true)
            ->assertJsonMissingPath('data.token')
            ->json('data.challenge');
    }

    public function test_setup_needs_the_password_and_a_code_before_it_is_in_force(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/user/two-factor', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');

        $setup = $this->postJson('/api/user/two-factor', ['password' => 'password'])->assertOk()->json('data');
        expect($setup['otpauth_url'])->toStartWith('otpauth://totp/')->toContain(urlencode('ada@example.com'));
        expect($setup['qr_code'])->toStartWith('data:image/svg+xml;base64,');

        // Not confirmed yet: nothing changes for signing in.
        $this->getJson('/api/user')->assertJsonPath('data.two_factor_enabled', false);
        $this->postJson('/api/user/two-factor/confirm', ['code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertJsonStructure(['data' => ['token']]);

        Sanctum::actingAs($user->refresh());
        $codes = $this->postJson('/api/user/two-factor/confirm', ['code' => $this->code($setup['secret'])])
            ->assertOk()
            ->assertJsonPath('data.user.two_factor_enabled', true)
            ->json('data.recovery_codes');

        expect($codes)->toHaveCount(TwoFactorService::RECOVERY_CODE_COUNT);
        expect($codes[0])->toMatch('/^[a-z0-9]{5}-[a-z0-9]{5}$/');
        $this->postJson('/api/user/two-factor', ['password' => 'password'])->assertStatus(409);
    }

    public function test_confirming_without_starting_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/user/two-factor/confirm', ['code' => '123456'])->assertStatus(409);
    }

    public function test_signing_in_takes_a_code_after_the_password(): void
    {
        [, $secret] = $this->enabledUser();

        $challenge = $this->challenge();

        // The challenge is not a token.
        $this->withToken($challenge)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();

        // The next code: confirming the setup used the current one.
        $token = $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret, 1)])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->json('data.token');
        $this->withToken($token)->getJson('/api/user')->assertOk();

        // A challenge works once.
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret, 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge');
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        [, $secret] = $this->enabledUser();
        $code = $this->code($secret, 1);

        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'code' => $code])->assertOk();

        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        // Nor an older one, even though it is still inside the clock-drift window.
        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'code' => $this->code($secret)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_a_recovery_code_works_once(): void
    {
        [$user, , $codes] = $this->enabledUser();

        // Typed in capitals and without the dash, it still counts.
        $typed = strtoupper(str_replace('-', '', $codes[3]));
        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'recovery_code' => $typed])->assertOk();
        expect(app(TwoFactorService::class)->remainingRecoveryCodes($user->refresh()))->toBe(TwoFactorService::RECOVERY_CODE_COUNT - 1);

        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'recovery_code' => $codes[3]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recovery_code');
    }

    public function test_recovery_codes_and_the_secret_are_never_stored_in_the_clear(): void
    {
        [$user, $secret, $codes] = $this->enabledUser();

        $row = (array) DB::table('users')->where('id', $user->id)->first();
        expect($row['two_factor_secret'])->not->toContain($secret);
        expect($row['two_factor_recovery_codes'])->not->toContain($codes[0]);
        expect($user->two_factor_recovery_codes)->not->toContain($codes[0]);

        Sanctum::actingAs($user);
        $this->getJson('/api/user')->assertJsonMissingPath('data.two_factor_secret')->assertJsonPath('data.two_factor_enabled', true);
    }

    public function test_five_wrong_codes_end_the_sign_in(): void
    {
        [, $secret] = $this->enabledUser();
        $challenge = $this->challenge();

        foreach (range(1, TwoFactorService::CHALLENGE_MAX_ATTEMPTS - 1) as $attempt) {
            $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])->assertJsonValidationErrors('code');
        }
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])
            ->assertJsonValidationErrors(['challenge' => 'Too many wrong codes']);

        expect(app(TwoFactorService::class)->challengeUser($challenge))->toBeNull();
        expect($this->code($secret))->not->toBe('000000');
    }

    public function test_a_challenge_expires(): void
    {
        [, $secret] = $this->enabledUser();
        $challenge = $this->challenge();

        $this->travel(TwoFactorService::CHALLENGE_TTL_SECONDS + 1)->seconds();

        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret, 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge' => 'expired']);
    }

    public function test_turning_it_off_needs_the_password(): void
    {
        [$user] = $this->enabledUser();
        $challenge = $this->challenge();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/user/two-factor', ['password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/user/two-factor', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);
        expect($user->refresh()->two_factor_secret)->toBeNull();

        // A challenge from before is void, and signing in is one step again.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'recovery_code' => 'aaaaa-aaaaa'])
            ->assertJsonValidationErrors('challenge');
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertJsonStructure(['data' => ['token']]);
    }

    public function test_new_recovery_codes_replace_the_old_ones(): void
    {
        [$user, , $old] = $this->enabledUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/user/two-factor/recovery-codes', ['password' => 'wrong'])->assertUnprocessable();
        $new = $this->postJson('/api/user/two-factor/recovery-codes', ['password' => 'password'])->assertOk()->json('data.recovery_codes');
        expect($new)->toHaveCount(TwoFactorService::RECOVERY_CODE_COUNT)->not->toContain($old[0]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'recovery_code' => $old[0]])->assertJsonValidationErrors('recovery_code');
        $this->postJson('/api/login/two-factor', ['challenge' => $this->challenge(), 'recovery_code' => $new[0]])->assertOk();
    }

    public function test_recovery_codes_need_it_on(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/user/two-factor/recovery-codes', ['password' => 'password'])->assertStatus(409);
    }

    public function test_people_behind_one_ip_do_not_share_a_tiny_limit(): void
    {
        foreach (range(1, 6) as $n) {
            $user = User::factory()->create(['email' => "user{$n}@example.com"]);
            Sanctum::actingAs($user);
            $secret = $this->postJson('/api/user/two-factor', ['password' => 'password'])->json('data.secret');
            $this->postJson('/api/user/two-factor/confirm', ['code' => $this->code($secret)])->assertOk();
            $this->app['auth']->forgetGuards();

            $challenge = $this->postJson('/api/login', ['email' => "user{$n}@example.com", 'password' => 'password'])->json('data.challenge');
            $this->postJson('/api/login/two-factor', ['challenge' => $challenge, 'code' => $this->code($secret, 1)])->assertOk();
        }
    }

    public function test_an_operator_can_turn_it_off_for_someone_locked_out(): void
    {
        [$user] = $this->enabledUser();
        $user->createToken('laptop');

        $this->artisan('two-factor:disable', ['email' => 'ada@example.com'])->assertSuccessful();

        expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
        expect($user->tokens()->count())->toBe(0);
        $this->postJson('/api/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertJsonStructure(['data' => ['token']]);
        $this->artisan('two-factor:disable', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
