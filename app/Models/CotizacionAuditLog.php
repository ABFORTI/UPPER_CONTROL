<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionAuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'cotizacion_id',
        'action',
        'actor_user_id',
        'actor_client_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorClient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_client_id');
    }
}
