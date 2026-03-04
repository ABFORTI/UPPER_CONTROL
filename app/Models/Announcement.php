<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'video_type',
        'video_url',
        'video_path',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function views(): HasMany
    {
        return $this->hasMany(AnnouncementView::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetCentros(): BelongsToMany
    {
        return $this->belongsToMany(CentroTrabajo::class, 'announcement_centro_trabajo', 'announcement_id', 'centro_trabajo_id')
            ->withTimestamps();
    }

    public function targetRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_role', 'announcement_id', 'role_id')
            ->withTimestamps();
    }

    public function scopeActiveWithin(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $roleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->values()->all();

        $centroIds = $user->centros()->pluck('centros_trabajo.id')->map(fn ($id) => (int) $id)->values()->all();
        if ($user->centro_trabajo_id) {
            $centroIds[] = (int) $user->centro_trabajo_id;
        }
        $centroIds = array_values(array_unique(array_filter($centroIds, fn ($id) => $id > 0)));

        return $query
            ->where(function (Builder $q) use ($roleIds) {
                $q->whereDoesntHave('targetRoles')
                    ->orWhereHas('targetRoles', function (Builder $roleQ) use ($roleIds) {
                        if (empty($roleIds)) {
                            $roleQ->whereRaw('1 = 0');
                            return;
                        }

                        $roleQ->whereIn('roles.id', $roleIds);
                    });
            })
            ->where(function (Builder $q) use ($centroIds) {
                $q->whereDoesntHave('targetCentros')
                    ->orWhereHas('targetCentros', function (Builder $centroQ) use ($centroIds) {
                        if (empty($centroIds)) {
                            $centroQ->whereRaw('1 = 0');
                            return;
                        }

                        $centroQ->whereIn('centros_trabajo.id', $centroIds);
                    });
            });
    }

    public function videoSrc(): ?string
    {
        if ($this->video_type === 'upload') {
            return $this->video_path ? Storage::url($this->video_path) : null;
        }

        if (!$this->video_url) {
            return null;
        }

        return match ($this->video_type) {
            'youtube' => $this->youtubeEmbedUrl($this->video_url),
            'vimeo' => $this->vimeoEmbedUrl($this->video_url),
            default => $this->video_url,
        };
    }

    private function youtubeEmbedUrl(string $url): string
    {
        $value = trim($url);
        if (preg_match('~(?:youtube\.com/watch\?v=|youtube\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $value, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        return $value;
    }

    private function vimeoEmbedUrl(string $url): string
    {
        $value = trim($url);
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $value, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return $value;
    }
}
