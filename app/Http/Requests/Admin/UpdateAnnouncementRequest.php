<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->hasRole('admin') === true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'video_type' => ['required', Rule::in(['upload', 'youtube', 'vimeo', 'url'])],
            'video_url' => ['nullable', 'url', 'max:2048', 'required_unless:video_type,upload'],
            'video_file' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/ogg',
                'max:' . (int) config('announcements.max_upload_kb', 204800),
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'target_centros' => ['nullable', 'array'],
            'target_centros.*' => ['integer', Rule::exists('centros_trabajo', 'id')],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }
}
