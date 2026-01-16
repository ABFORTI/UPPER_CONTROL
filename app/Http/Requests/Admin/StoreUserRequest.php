<?php

// app/Http/Requests/Admin/StoreUserRequest.php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest {
    public function authorize(): bool { return Auth::user()?->hasRole('admin') === true; }
    public function rules(): array {
        return [
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:150','unique:users,email'],
            'phone'  => ['nullable','string','max:30'],
            'password' => ['required','string','min:8','confirmed'],
            'centro_trabajo_id' => ['required','integer','exists:centros_trabajo,id'],
            'roles'   => ['required','array','min:1'],
            'roles.*' => ['string', Rule::exists('roles','name')->where('guard_name','web')],
            // Centros múltiples (solo aplica si role es calidad o facturacion, pero lo dejamos opcional)
            'centros_ids'   => ['sometimes','array'],
            'centros_ids.*' => ['integer','exists:centros_trabajo,id'],
        ];
    }
}
