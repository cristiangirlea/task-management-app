<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

/**
 * A tenant is a workspace: the unit of data isolation. Users, projects and
 * tasks all belong to exactly one tenant.
 *
 * It is also the paying customer: the Stripe subscription belongs to the
 * workspace, billed per member (see BillingService).
 */
class Tenant extends Model
{
    use Billable, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'settings',
    ];

    protected $hidden = [
        'stripe_id',
        'pm_type',
        'pm_last_four',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Receipts and Stripe's own emails go to the owner; a workspace has no
     * address of its own.
     */
    public function stripeEmail(): ?string
    {
        return $this->users()->where('role', User::ROLE_OWNER)->oldest('id')->value('email');
    }

    public function scopeByDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Build a slug from a name that is not yet used by another tenant.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
