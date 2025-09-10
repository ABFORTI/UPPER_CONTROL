<?php

// app/Http/Requests/Admin/UpdateUserRequest.php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest {
    public function authorize(): bool { return $this->user()->hasRole('admin'); }
    public function rules(): array {
        $id = $this->route('user')->id;
        return [
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:150', Rule::unique('users','email')->ignore($id)],
            'phone'  => ['nullable','string','max:30'],
            'centro_trabajo_id' => ['required','integer','exists:centros_trabajo,id'],
            'role'   => ['required','string','in:admin,coordinador,team_leader,calidad,facturacion,cliente'],
            'password' => ['nullable','string','min:8','confirmed'],
        ];
    }
}
