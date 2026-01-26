<?php

namespace App\Http\Requests\Api\Quotations;

use Illuminate\Foundation\Http\FormRequest;

class IndexQuotationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\Cotizacion::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:20'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:200'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
