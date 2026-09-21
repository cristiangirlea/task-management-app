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
 */
class Invitation extends Model
{
    use BelongsToTenant, HasFactory;

    public const LIFETIME_DAYS = 7;

    protected $fillable = [
        'tenant_id',
        'email',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = ['token'];

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
            $invitation->token ??= Str::random(64);
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
     * Public lookup by token (no authenticated user, so no tenant scope).
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScopes()->where('token', $token)->first();
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

    public function acceptUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/invite/'.$this->token;
    }
}
