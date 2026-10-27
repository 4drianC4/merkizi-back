<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\RawMaterialCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreRawMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RawMaterialCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
        ];
    }
}