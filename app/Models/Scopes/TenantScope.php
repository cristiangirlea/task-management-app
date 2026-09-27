<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-owned model to the tenant of the
 * authenticated user.
 *
 * With no authenticated user at all (console commands, seeders, factories)
 * the scope steps aside. But an authenticated user who somehow has no
 * workspace sees nothing rather than everything: failing open there would
 * expose every tenant's data, and a database upgraded from a release that
 * did not set tenant_id would contain exactly such users.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        if ($user->tenant_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $user->tenant_id);
    }

    public static function currentTenantId(): ?int
    {
        $user = auth()->user();

        return $user?->tenant_id;
    }
}
