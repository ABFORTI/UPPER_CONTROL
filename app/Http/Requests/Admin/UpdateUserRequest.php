<?php

// app/Http/Requests/Admin/UpdateUserRequest.php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UpdateUserRequest extends FormRequest {
    public function authorize(): bool { return Auth::user()?->hasRole('admin') === true; }
    public function rules(): array {
        $routeUser = request()->route('user');
        $id = $routeUser instanceof User ? $routeUser->id : null;
        return [
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:150', Rule::unique('users','email')->ignore($id)],
            'phone'  => ['nullable','string','max:30'],
            'centro_trabajo_id' => ['required','integer','exists:centros_trabajo,id'],
            'roles'   => ['required','array','min:1'],
            'roles.*' => ['string', Rule::exists('roles','name')->where('guard_name','web')],
            'password' => ['nullable','string','min:8','confirmed'],
            'centros_ids'   => ['sometimes','array'],
            'centros_ids.*' => ['integer','exists:centros_trabajo,id'],
        ];
    }
}
