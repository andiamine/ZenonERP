<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateCompany;
use Modules\Core\Actions\DeleteCompany;
use Modules\Core\Actions\UpdateCompany;
use Modules\Core\Http\Requests\StoreCompanyRequest;
use Modules\Core\Http\Requests\UpdateCompanyRequest;
use Modules\Core\Http\Resources\CompanyResource;
use Modules\Core\Models\Company;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CompaniesController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
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

        return CompanyResource::collection($companies);
    }

    public function show(Company $company): CompanyResource
    {
        return CompanyResource::make($company);
    }

    public function store(StoreCompanyRequest $request, CreateCompany $action): JsonResponse
    {
        $company = $action->handle($request->validated());

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
