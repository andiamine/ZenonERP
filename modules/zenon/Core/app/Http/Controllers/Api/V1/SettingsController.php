<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use App\Foundation\Company\CurrentCompany;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\PutSettings;
use Modules\Core\Contracts\Settings\Exceptions\InvalidSettingValueException;
use Modules\Core\Contracts\Settings\Exceptions\UnknownSettingException;
use Modules\Core\Contracts\Settings\SettingDefinition;
use Modules\Core\Contracts\Settings\SettingsReader;
use Modules\Core\Contracts\Settings\SettingsRegistrar;
use Modules\Core\Http\Requests\PutSettingsRequest;

class SettingsController extends ApiController
{
    public function index(SettingsReader $reader, CurrentCompany $currentCompany): JsonResponse
    {
        return response()->json(['data' => $reader->all($currentCompany->id())]);
    }

    /**
     * Same gate as SettingsRepository (CLAUDE.md §6/§13 risk #1): a definition owned by a
     * module disabled for the current tenant is invisible here too, not just in the
     * effective values map.
     */
    public function definitions(SettingsRegistrar $registrar, ModuleRegistry $modules): JsonResponse
    {
        $definitions = array_values(array_map(
            static fn (SettingDefinition $definition): array => [
                'key' => $definition->key,
                'type' => $definition->type,
                'default' => $definition->default,
                'label' => $definition->label,
            ],
            array_filter(
                $registrar->definitions(),
                static fn (SettingDefinition $definition): bool => $definition->module === null
                    || $modules->isEnabledForCurrentTenant($definition->module),
            ),
        ));

        return response()->json(['data' => $definitions]);
    }

    /**
     * Maps the two Contracts settings exceptions (thrown per-key by PutSettings) to the
     * standard §8 422 envelope, attributed to the exact `values.<key>` field — see
     * PutSettings's docblock for why the loop lives here, not inside the Action.
     */
    public function update(
        PutSettingsRequest $request,
        PutSettings $action,
        SettingsReader $reader,
        CurrentCompany $currentCompany,
    ): JsonResponse {
        /** @var array<string, mixed> $values */
        $values = $request->validated('values');

        foreach ($values as $key => $value) {
            try {
                $action->handle((string) $key, $value, $currentCompany->id());
            } catch (UnknownSettingException|InvalidSettingValueException $e) {
                throw ValidationException::withMessages(['values.'.$key => $e->getMessage()]);
            }
        }

        return response()->json(['data' => $reader->all($currentCompany->id())]);
    }
}
