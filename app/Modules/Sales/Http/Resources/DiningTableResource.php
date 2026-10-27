<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiningTable */
class DiningTableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $account = $this->activeAccounts->first();

        return ['id' => $this->id, 'branch_id' => $this->branch_id, 'area_id' => $this->area_id,
            'area' => $this->whenLoaded('area', fn () => ['id' => $this->area->id, 'name' => $this->area->name]),
            'number' => $this->number, 'capacity' => $this->capacity, 'active' => $this->active, 'blocked' => $this->blocked,
            'status' => $account !== null ? 'occupied' : ((! $this->active || $this->blocked) ? 'blocked' : 'free'),
            'active_account' => $account === null ? null : ['id' => $account->id, 'version' => $account->version, ...$account->totals()]];
    }
}
