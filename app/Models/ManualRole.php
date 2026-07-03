<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualRole extends Model
{
    protected $table = 'manual_role';

    protected $fillable = [
        'manual_id',
        'role',
    ];

    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }
}
