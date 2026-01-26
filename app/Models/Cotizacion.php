<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    protected $fillable = [
        'folio',
        'created_by',
        'id_cliente',
        'id_centrotrabajo',
        'id_centrocosto',
        'id_marca',
        'id_area',
        'currency',
        'subtotal',
        'tax',
        'iva',
        'total',
        'estatus',
        'sent_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'expires_at',
        'approval_token_hash',
        'notas',
        'notes',
        'motivo_rechazo',
    ];

    protected $casts = [
        'tax' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'sent_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public const ESTATUS_DRAFT = 'draft';
    public const ESTATUS_SENT = 'sent';
    public const ESTATUS_APPROVED = 'approved';
    public const ESTATUS_REJECTED = 'rejected';
    public const ESTATUS_EXPIRED = 'expired';
    public const ESTATUS_CANCELLED = 'cancelled';

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_cliente');
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroTrabajo::class, 'id_centrotrabajo');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'id_centrocosto');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'id_marca');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class, 'cotizacion_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CotizacionAuditLog::class, 'cotizacion_id');
    }

    public function isExpired(): bool
    {
        if ($this->estatus !== self::ESTATUS_SENT) return false;
        if (!$this->expires_at) return false;
        return now()->greaterThan($this->expires_at);
    }
}
