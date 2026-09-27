<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation to join a workspace. The token is the shareable secret in the
 * accept link; it is single use and expires.
 *
 * Only a SHA-256 hash of the token is stored. The plain token exists on the
 * model that issued it (creating, or issueToken() when re-sending), for the
 * email and for the owner who just sent it, and nowhere else.
 */
class Invitation extends Model
{
    use BelongsToTenant, HasFactory;

    public const LIFETIME_DAYS = 7;

    protected $fillable = [
        'tenant_id',
        'email',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = ['token_hash'];

    /** The token issued by this model instance, if any; never persisted. */
    public ?string $plainToken = null;

    /** Whether the email carrying $plainToken went out; null when none was sent. */
    public ?bool $emailSent = null;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            if (empty($invitation->token_hash)) {
                $invitation->issueToken();
            }
            $invitation->expires_at ??= now()->addDays(self::LIFETIME_DAYS);
        });
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * Give the invitation a new secret, replacing any previous one, and
     * return it. Not saved here.
     */
    public function issueToken(): string
    {
        $this->plainToken = Str::random(64);
        $this->token_hash = static::hashToken($this->plainToken);

        return $this->plainToken;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Public lookup by token (no authenticated user, so no tenant scope).
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScopes()->where('token_hash', static::hashToken($token))->first();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'accepted',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }

    /**
     * The link to accept, or null when this model did not issue the token
     * (only its hash is stored).
     */
    public function acceptUrl(): ?string
    {
        if ($this->plainToken === null) {
            return null;
        }

        return rtrim((string) config('app.frontend_url'), '/').'/invite/'.$this->plainToken;
    }
}
