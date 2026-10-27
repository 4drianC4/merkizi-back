<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class SearchCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Category::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}