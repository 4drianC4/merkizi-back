<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission($this->routeIs('tables.merge') ? 'salon.mesas.unir' : 'salon.mesas.transferir');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['source_account_id' => ['required', 'uuid'], 'destination_account_id' => ['required', 'uuid', 'different:source_account_id'],
            'source_version' => ['required', 'integer', 'min:1'], 'destination_version' => ['required', 'integer', 'min:1'],
            'transfer_all' => ['sometimes', 'boolean'], 'items' => [$this->routeIs('tables.merge') ? 'sometimes' : 'required_unless:transfer_all,true', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:id,quantity'], 'items.*.id' => ['required', 'uuid', 'distinct'], 'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:99999']];
    }
}
