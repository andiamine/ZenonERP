<?php

namespace Modules\Sequence\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Modules\Sequence\Models\Sequence;
use Modules\Sequence\Support\MaskFormatter;

/** @mixin Sequence */
class SequenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'company_id' => $this->company_id,
            'mask' => $this->mask,
            'next_number' => $this->next_number,
            'reset_period' => $this->reset_period,
            'current_period' => $this->current_period,
            'gapless' => $this->gapless,
            'preview' => $this->preview(),
        ];
    }

    /**
     * Read-only render of the value the NEXT allocation would produce, WITHOUT consuming
     * it (no counter mutation). Best-effort: it uses the stored current_period rather than
     * recomputing the fiscal period, so it never touches SettingsReader per row.
     */
    private function preview(): string
    {
        $companyCode = '';

        // Only look companies up when the mask actually needs it (avoids a query per row
        // for the common tenant-wide / no-{company} case). Direct table read is the same
        // sanctioned cross-module exception documented on SequenceService::companyCode().
        if ($this->company_id !== null && str_contains((string) $this->mask, '{company}')) {
            $companyCode = (string) DB::table('companies')->where('id', $this->company_id)->value('code');
        }

        return MaskFormatter::format($this->mask, $this->next_number, $companyCode, $this->current_period);
    }
}
