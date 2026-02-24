<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtCorte extends Model
{
    protected $table = 'ot_cortes';

    protected $fillable = [
        'ot_id',
        'periodo_inicio',
        'periodo_fin',
        'folio_corte',
        'estatus',
        'monto_total',
        'created_by',
        'ot_hija_id',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin'    => 'date',
        'monto_total'    => 'decimal:2',
    ];

    /* ── Relaciones ── */

    public function ot(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'ot_id');
    }

    public function otHija(): BelongsTo
    {
        return $this->belongsTo(Orden::class, 'ot_hija_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OtCorteDetalle::class, 'ot_corte_id');
    }

    /* ── Helpers ── */

    /**
     * Genera un folio único: CORTE-{OT_ID}-{YYMMDD}-{SECUENCIAL}
     */
    public static function generarFolio(int $otId): string
    {
        $prefix = 'CORTE-' . $otId . '-' . now()->format('ymd');
        $last   = static::where('folio_corte', 'like', $prefix . '%')
                        ->orderByDesc('folio_corte')
                        ->value('folio_corte');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq   = ((int) end($parts)) + 1;
        }

        return $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /* ── Estatus helpers ── */

    public function isDraft(): bool
    {
        return $this->estatus === 'draft';
    }

    public function markReadyToBill(): void
    {
        $this->update(['estatus' => 'ready_to_bill']);
    }

    public function markBilled(): void
    {
        $this->update(['estatus' => 'billed']);
    }

    public function markVoid(): void
    {
        $this->update(['estatus' => 'void']);
    }
}
