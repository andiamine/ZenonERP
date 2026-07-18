<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateCurrency;
use Modules\Core\Actions\DeleteCurrency;
use Modules\Core\Actions\UpdateCurrency;
use Modules\Core\Http\Requests\StoreCurrencyRequest;
use Modules\Core\Http\Requests\UpdateCurrencyRequest;
use Modules\Core\Http\Resources\CurrencyResource;
use Modules\Core\Models\Currency;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CurrenciesController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $currencies = QueryBuilder::for(Currency::class)
            ->allowedFilters(
                AllowedFilter::exact('code'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('active'),
            )
            ->allowedSorts('code', 'name')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return CurrencyResource::collection($currencies);
    }

    public function show(Currency $currency): CurrencyResource
    {
        return CurrencyResource::make($currency);
    }

    public function store(StoreCurrencyRequest $request, CreateCurrency $action): JsonResponse
    {
        $currency = $action->handle($request->validated());

        return CurrencyResource::make($currency)->response()->setStatusCode(201);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency, UpdateCurrency $action): CurrencyResource
    {
        return CurrencyResource::make($action->handle($currency, $request->validated()));
    }

    public function destroy(Currency $currency, DeleteCurrency $action): Response
    {
        $action->handle($currency);

        return $this->noContent();
    }
}
