<?php

namespace Modules\Audit\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Http\Resources\ActivityResource;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivitiesController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // ->with('causer') deliberately comes AFTER every Spatie\QueryBuilder-specific
        // call (allowedFilters/allowedSorts/defaultSort): QueryBuilder::__call() forwards
        // unrecognised methods to the underlying Eloquent Builder via its @mixin
        // annotation, which collapses the static return type to plain Builder — a QueryBuilder-only
        // method called after that point would no longer resolve for PHPStan.
        $activities = QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::callback('from', function (Builder $query, mixed $value): Builder {
                    return $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('to', function (Builder $query, mixed $value): Builder {
                    return $query->where('created_at', '<=', $value);
                }),
            )
            ->allowedSorts('created_at', 'id')
            ->defaultSort('-created_at')
            ->with('causer')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return ActivityResource::collection($activities);
    }
}
