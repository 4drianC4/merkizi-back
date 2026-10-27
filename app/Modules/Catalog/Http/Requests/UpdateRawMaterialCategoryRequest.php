<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRawMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('raw_material_category'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ];
    }
}
