<?php

// app/Http/Requests/Admin/StoreUserRequest.php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest {
    public function authorize(): bool { return $this->user()->hasRole('admin'); }
    public function rules(): array {
        return [
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:150','unique:users,email'],
            'phone'  => ['nullable','string','max:30'],
            'password' => ['required','string','min:8','confirmed'],
            'centro_trabajo_id' => ['required','integer','exists:centros_trabajo,id'],
            'role'   => ['required','string','in:admin,coordinador,team_leader,calidad,facturacion,cliente'],
        ];
    }
}
