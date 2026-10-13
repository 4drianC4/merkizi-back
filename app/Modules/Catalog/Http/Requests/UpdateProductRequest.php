<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:64'],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'price' => ['sometimes', 'nullable', 'decimal:0,2'],
            'status' => ['sometimes', 'required', 'string', 'in:active,inactive'],
        ];
    }
}
