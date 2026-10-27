<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\RawMaterialCategory;
use Illuminate\Foundation\Http\FormRequest;

class SearchRawMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', RawMaterialCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
