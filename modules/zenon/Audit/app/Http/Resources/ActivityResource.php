<?php

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            // Eager-loaded by the controller (->with('causer')) — never lazy-loads per row.
            'causer' => $this->causer !== null
                ? ['id' => $this->causer->getKey(), 'name' => $this->causer->name ?? null]
                : null,
            // As stored by LogsActivity::attributeValuesToBeLogged(): {attributes: {...}}
            // on create/delete, {attributes: {...}, old: {...}} (dirty keys only) on update.
            'properties' => $this->properties?->toArray() ?? [],
            'batch_uuid' => $this->batch_uuid,
            'created_at' => $this->created_at,
        ];
    }
}
