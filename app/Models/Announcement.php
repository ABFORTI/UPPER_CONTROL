<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
