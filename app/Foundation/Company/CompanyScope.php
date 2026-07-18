<?php

namespace App\Foundation\Company;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope applied by BelongsToCompany (CLAUDE.md §9.3).
 *
 * Semantics: NULL company_id = shared row, visible under every company (master data,
 * Odoo-style `company_id = null`); transactional tables carry a NOT NULL company_id, so
 * the whereNull branch is vacuous there and the scope behaves as a plain equality filter.
 * When no company is currently active (CurrentCompany::id() is null — central context,
 * platform operators, company-less tenants) the query is left UNCONSTRAINED, not
 * "shared-only": scoping is opt-in per request, not a blanket restriction.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $id = app(CurrentCompany::class)->id();

        if ($id === null) {
            return;
        }

        $builder->where(fn ($q) => $q->whereNull($model->qualifyColumn('company_id'))
            ->orWhere($model->qualifyColumn('company_id'), $id));
    }

    /**
     * Registers `withoutCompanyScope()` — the escape hatch for cross-company admin/report
     * queries. Eloquent Builder's per-instance macro mechanism (Builder::__call) unshifts
     * the calling builder as the closure's first argument WITHOUT rebinding the closure's
     * own $this, so a plain `$this` inside the closure would still resolve correctly at
     * runtime — but capturing it explicitly as $scope keeps the static type unambiguous.
     *
     * @param  Builder<TModel>  $builder
     */
    public function extend(Builder $builder): void
    {
        $scope = $this;

        $builder->macro('withoutCompanyScope', function (Builder $builder) use ($scope) {
            return $builder->withoutGlobalScope($scope);
        });
    }
}
