<?php

namespace App\Models\Concerns;

use App\Models\KnowledgeSource;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\SocialAccount;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $model->organization_id = $model->inferOrganizationId();
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** Child rows inherit the organization of the account / source they hang off. */
    protected function inferOrganizationId(): ?int
    {
        if ($accountId = $this->getAttribute('social_account_id')) {
            $id = SocialAccount::withoutGlobalScopes()->whereKey($accountId)->value('organization_id');
            if ($id) {
                return (int) $id;
            }
        }

        if ($sourceId = $this->getAttribute('knowledge_source_id')) {
            $id = KnowledgeSource::withoutGlobalScopes()->whereKey($sourceId)->value('organization_id');
            if ($id) {
                return (int) $id;
            }
        }

        return auth()->user()?->organization_id;
    }
}
