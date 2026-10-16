<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('cashSession'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'difference_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
