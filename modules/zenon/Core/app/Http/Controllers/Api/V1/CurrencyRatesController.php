<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\CreateCurrencyRate;
use Modules\Core\Http\Requests\StoreCurrencyRateRequest;
use Modules\Core\Http\Resources\CurrencyRateResource;
use Modules\Core\Models\Currency;
use Modules\Core\Models\CurrencyRate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrencyRatesController extends ApiController
{
    public function index(Request $request, Currency $currency): AnonymousResourceCollection
    {
        $rates = QueryBuilder::for(CurrencyRate::query()->where('currency_id', $currency->getKey()))
            ->allowedFilters(
                AllowedFilter::callback('from', function (Builder $query, mixed $value): Builder {
                    return $query->where('valid_from', '>=', $value);
                }),
                AllowedFilter::callback('to', function (Builder $query, mixed $value): Builder {
                    return $query->where('valid_from', '<=', $value);
                }),
            )
            ->allowedSorts('valid_from')
            ->defaultSort('-valid_from')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return CurrencyRateResource::collection($rates);
    }

    public function store(StoreCurrencyRateRequest $request, Currency $currency, CreateCurrencyRate $action): JsonResponse
    {
        $rate = $action->handle($currency, $request->validated());

        return CurrencyRateResource::make($rate)->response()->setStatusCode(201);
    }
}
