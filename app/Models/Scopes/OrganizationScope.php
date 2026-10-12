<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Safety net for tenant isolation: while a regular (non super-admin) user is
 * signed in, every query on a tenant model is limited to their organization,
 * even if a controller forgets to filter. Webhooks, queued jobs and console
 * commands have no signed-in user and are therefore unaffected.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || $user->is_super_admin || ! $user->organization_id) {
            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $user->organization_id);
    }
}
