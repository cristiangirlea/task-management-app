<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-owned model to the tenant of the
 * authenticated user. When there is no authenticated user (console
 * commands, seeders, unit tests without a user) no constraint is applied.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = static::currentTenantId();

        if ($tenantId !== null) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }

    public static function currentTenantId(): ?int
    {
        $user = auth()->user();

        return $user?->tenant_id;
    }
}
