<?php

namespace App\Services\OAuth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\AuthCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * The MCP clients someone has allowed into their account. An app stays
 * connected while it holds an access token that has not expired, or a
 * refresh token to get a new one. Disconnecting deletes its tokens, which
 * Passport then treats as revoked.
 */
class OAuthConnections
{
    /**
     * @return Collection<int, array{id: string, name: string, redirect_host: string|null, connected_at: string|null}>
     */
    public function forUser(User $user): Collection
    {
        $live = Token::query()
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())))
            ->with('client')
            ->get()
            ->filter(fn (Token $token): bool => $token->client !== null && ! $token->client->revoked);

        $firstConnected = Token::query()
            ->where('user_id', $user->id)
            ->whereIn('client_id', $live->pluck('client_id')->unique())
            ->groupBy('client_id')
            ->selectRaw('client_id, min(created_at) as connected_at')
            ->pluck('connected_at', 'client_id');

        return $live->unique('client_id')
            ->map(fn (Token $token): array => [
                'id' => (string) $token->client_id,
                'name' => $token->client->name,
                'redirect_host' => self::redirectHost($token->client->redirect_uris),
                'connected_at' => isset($firstConnected[$token->client_id])
                    ? Carbon::parse($firstConnected[$token->client_id])->toIso8601String()
                    : null,
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Ends one app's access. False when it had none.
     */
    public function disconnect(User $user, string $clientId): bool
    {
        return $this->deleteTokens($user, $clientId) > 0;
    }

    /**
     * Ends every app's access, as a password change does.
     */
    public function disconnectAll(User $user): void
    {
        $this->deleteTokens($user, null);
    }

    /**
     * The host an app sends people back to, shown so a look-alike name
     * ("Claude" registered by someone else) can be told apart.
     *
     * @param  array<int, string>  $redirectUris
     */
    public static function redirectHost(array $redirectUris): ?string
    {
        $uri = $redirectUris[0] ?? null;

        return $uri === null ? null : (parse_url($uri, PHP_URL_HOST) ?: parse_url($uri, PHP_URL_SCHEME) ?: null);
    }

    private function deleteTokens(User $user, ?string $clientId): int
    {
        return DB::transaction(function () use ($user, $clientId): int {
            $tokens = Token::query()
                ->where('user_id', $user->id)
                ->when($clientId !== null, fn (Builder $query) => $query->where('client_id', $clientId));
            $ids = $tokens->pluck('id');

            RefreshToken::query()->whereIn('access_token_id', $ids)->delete();
            AuthCode::query()
                ->where('user_id', $user->id)
                ->when($clientId !== null, fn (Builder $query) => $query->where('client_id', $clientId))
                ->delete();

            return Token::query()->whereKey($ids)->delete();
        });
    }
}
