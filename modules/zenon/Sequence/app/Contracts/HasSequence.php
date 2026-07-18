<?php

namespace Modules\Sequence\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Opt-in document numbering for a model (CLAUDE.md §9.2, the MIX binding). On `creating`,
 * if the sequence column (default `number`) is empty it is filled with the next value for
 * the model's sequenceCode(), scoped to the model's company_id when present.
 *
 * No runtime enablement gate is needed here: a consumer module declares
 * `requires: { "sequence": "..." }`, so DependencyResolver guarantees the consumer can
 * only be enabled for a tenant where `sequence` is also enabled — the binding below is
 * therefore always resolvable in that context.
 *
 * @mixin Model
 */
trait HasSequence
{
    public static function bootHasSequence(): void
    {
        static::creating(function (Model $model): void {
            // Eloquent only fires this on instances of the using class; the guard narrows
            // $model to that type so sequenceCode()/sequenceColumn() are in scope.
            if (! $model instanceof self) {
                return;
            }

            $column = static::sequenceColumn();

            if (! empty($model->getAttribute($column))) {
                return;
            }

            $companyId = $model->getAttribute('company_id');
            $companyId = is_numeric($companyId) ? (int) $companyId : null;

            $model->setAttribute(
                $column,
                app(SequenceGenerator::class)->next($model->sequenceCode(), $companyId),
            );
        });
    }

    /** The registered sequence code this model draws numbers from. */
    abstract public function sequenceCode(): string;

    /** Model attribute the generated number is written to. Override to relocate it. */
    protected static function sequenceColumn(): string
    {
        return 'number';
    }
}
