<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use App\Foundation\Hooks\HookBus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateCompany;
use Modules\Core\Actions\DeleteCompany;
use Modules\Core\Actions\UpdateCompany;
use Modules\Core\Contracts\Hooks\CompanyApiResponse;
use Modules\Core\Http\Requests\StoreCompanyRequest;
use Modules\Core\Http\Requests\UpdateCompanyRequest;
use Modules\Core\Http\Resources\CompanyResource;
use Modules\Core\Models\Company;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CompaniesController extends ApiController
{
    public function index(Request $request, HookBus $hooks): AnonymousResourceCollection
    {
        $companies = QueryBuilder::for(Company::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('code'),
                AllowedFilter::exact('active'),
            )
            ->allowedSorts('name', 'code', 'id')
            ->defaultSort('name')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        $snapshot = array_values($companies->getCollection()
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code])
            ->all());
        $payload = $hooks->filter(new CompanyApiResponse($snapshot));

        return CompanyResource::collection($companies)->additional(['extra' => (object) $payload->extra]);
    }

    public function show(Company $company, HookBus $hooks): CompanyResource
    {
        $snapshot = [['id' => $company->id, 'name' => $company->name, 'code' => $company->code]];
        $payload = $hooks->filter(new CompanyApiResponse($snapshot));

        return CompanyResource::make($company)->additional(['extra' => (object) $payload->extra]);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $action): JsonResponse
    {
        /** @var User $actingUser */
        $actingUser = $request->user();

        $company = $action->handle($request->validated(), $actingUser);

        return CompanyResource::make($company)->response()->setStatusCode(201);
    }

    public function update(UpdateCompanyRequest $request, Company $company, UpdateCompany $action): CompanyResource
    {
        return CompanyResource::make($action->handle($company, $request->validated()));
    }

    public function destroy(Company $company, DeleteCompany $action): Response
    {
        $action->handle($company);

        return $this->noContent();
    }
}
