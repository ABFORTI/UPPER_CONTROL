<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manual extends Model
{
    protected $fillable = [
        'titulo',
        'descripcion',
        'archivo_pdf',
        'visible_para_todos',
        'uploaded_by',
    ];

    protected $casts = [
        'visible_para_todos' => 'boolean',
    ];

    public function roles(): HasMany
    {
        return $this->hasMany(ManualRole::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $roles = $user->getRoleNames()->values()->all();

        return $query->where(function (Builder $q) use ($roles) {
            $q->where('visible_para_todos', true)
                ->orWhereHas('roles', function (Builder $roleQ) use ($roles) {
                    if (empty($roles)) {
                        $roleQ->whereRaw('1 = 0');
                        return;
                    }

                    $roleQ->whereIn('role', $roles);
                });
        });
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($this->visible_para_todos) {
            return true;
        }

        $roles = $user->getRoleNames()->values()->all();

        return $this->roles()->whereIn('role', $roles)->exists();
    }
}
