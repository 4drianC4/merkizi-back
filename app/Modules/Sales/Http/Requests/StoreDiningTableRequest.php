<?php

namespace App\Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('salon.mesas.crear');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['area_id' => ['required', 'uuid'], 'number' => ['required', 'string', 'max:20'], 'capacity' => ['sometimes', 'integer', 'min:1', 'max:999'], 'blocked' => ['sometimes', 'boolean']];
    }
}
