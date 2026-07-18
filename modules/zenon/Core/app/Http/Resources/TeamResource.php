<?php

namespace Modules\Core\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Models\Team;

/** @mixin Team */
class TeamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'active' => $this->active,
            'users' => $this->whenLoaded('users', fn () => UserResource::collection($this->users)),
        ];
    }
}
