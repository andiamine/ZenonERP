<?php

namespace Modules\Sequence\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Sequence\Contracts\SequenceDefinition;
use Modules\Sequence\Contracts\SequenceRegistrar;
use Modules\Sequence\Http\Requests\UpdateSequenceRequest;
use Modules\Sequence\Http\Resources\SequenceResource;
use Modules\Sequence\Models\Sequence;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SequencesController extends ApiController
{
    /** Paginated MATERIALISED counter rows (a row exists once a code has been drawn). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sequences = QueryBuilder::for(Sequence::class)
            ->allowedFilters(
                AllowedFilter::exact('code'),
                AllowedFilter::exact('company_id'),
            )
            ->allowedSorts('code', 'id')
            ->defaultSort('code')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return SequenceResource::collection($sequences);
    }

    /**
     * ALL registered definitions with a `materialized` flag — the honest counterpart to
     * index(): a registered code that has never been drawn has no row yet, so it would be
     * invisible to /sequences. This lists the declared shapes regardless.
     */
    public function definitions(SequenceRegistrar $registrar): JsonResponse
    {
        /** @var list<string> $materializedCodes */
        $materializedCodes = Sequence::query()->distinct()->pluck('code')->all();

        $data = array_values(array_map(
            static fn (SequenceDefinition $definition): array => [
                'code' => $definition->code,
                'mask' => $definition->mask,
                'reset_period' => $definition->resetPeriod,
                'per_company' => $definition->perCompany,
                'gapless' => $definition->gapless,
                'label' => $definition->label,
                'materialized' => in_array($definition->code, $materializedCodes, true),
            ],
            $registrar->all(),
        ));

        return response()->json(['data' => $data]);
    }

    public function update(UpdateSequenceRequest $request, Sequence $sequence): SequenceResource
    {
        // Only mask/reset_period are fillable here; the counter is never hand-edited.
        $sequence->fill($request->validated())->save();

        return SequenceResource::make($sequence);
    }
}
