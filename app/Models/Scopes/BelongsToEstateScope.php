<?php

namespace App\Models\Scopes;

use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope isolasi per-estate. Model memakai trait BelongsToEstate
 * dan dapat meng-override applyEstateScope() untuk kolom turunan
 * (mis. Billing menyaring lewat house).
 */
class BelongsToEstateScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $estateId = CurrentEstate::id();

        if ($estateId === null) {
            // Platform / tamu / CLI → tanpa filter.
            return;
        }

        $model->applyEstateScope($builder, $estateId);
    }
}
