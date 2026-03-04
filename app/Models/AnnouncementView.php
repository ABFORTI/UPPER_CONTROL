<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementView extends Model
{
    protected $fillable = [
        'announcement_id',
        'user_id',
        'seen_at',
        'dismissed_forever',
    ];

    protected $casts = [
        'seen_at' => 'datetime',
        'dismissed_forever' => 'boolean',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
