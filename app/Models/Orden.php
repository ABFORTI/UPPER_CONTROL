<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Orden extends Model {
    use LogsActivity;
    protected $table='ordenes_trabajo';
    protected $fillable = [
        'id_solicitud','id_centrotrabajo','id_servicio','id_area','team_leader_id',
        'descripcion_general',
        'estatus','calidad_resultado','total_planeado','total_real',
        'subtotal','iva','total',
        'motivo_rechazo','acciones_correctivas',
        // Campos de archivo Excel
        'archivo_excel_path','archivo_excel_nombre_original','archivo_excel_mime',
        'archivo_excel_size','archivo_excel_subido_por','archivo_excel_subido_at',
        // Campos de corte / OT hija
        'parent_ot_id','split_index','ot_status',
    ];
    public function solicitud(){ return $this->belongsTo(Solicitud::class,'id_solicitud'); }
    public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
    public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
    public function area(){ return $this->belongsTo(Area::class,'id_area'); }
    public function items(){ return $this->hasMany(OrdenItem::class,'id_orden'); }
    public function segmentosProduccion(){
        return $this->hasMany(\App\Models\OrdenItemProduccionSegmento::class,'id_orden');
    }
    public function avances(){ return $this->hasMany(Avance::class,'id_orden'); }
    public function teamLeader(){ return $this->belongsTo(User::class,'team_leader_id'); }
    public function archivoSubidoPor(){ return $this->belongsTo(User::class,'archivo_excel_subido_por'); }
    public function evidencias(){ return $this->hasMany(\App\Models\Evidencia::class,'id_orden'); }
    public function aprobaciones()
  
{
    return $this->morphMany(\App\Models\Aprobacion::class, 'aprobable');
}

    public function factura(){ return $this->hasOne(\App\Models\Factura::class,'id_orden'); }
    public function facturas(){
        return $this->belongsToMany(\App\Models\Factura::class, 'factura_orden', 'id_orden', 'id_factura');
    }

    /**
     * Relación con los servicios de la OT (múltiples servicios)
     */
    public function otServicios()
    {
        return $this->hasMany(OTServicio::class, 'ot_id');
    }

    /* ── Relaciones de Corte de OT ── */

    public function parentOt()
    {
        return $this->belongsTo(self::class, 'parent_ot_id');
    }

    public function childOts()
    {
        return $this->hasMany(self::class, 'parent_ot_id');
    }

    public function cortes()
    {
        return $this->hasMany(OtCorte::class, 'ot_id');
    }

    /**
     * Obtiene la OT raíz de la cadena de splits.
     */
    public function getRootOt(): self
    {
        $ot = $this;
        while ($ot->parent_ot_id) {
            $ot = $ot->parentOt;
        }
        return $ot;
    }

    /**
     * Siguiente split_index disponible para la cadena.
     */
    public function nextSplitIndex(): int
    {
        $root = $this->getRootOt();
        $max  = self::where('parent_ot_id', $root->id)
                    ->max('split_index') ?? 0;
        return $max + 1;
    }

    /**
     * Recalcular totales de la OT basados en los servicios
     */
    public function recalcTotals(): void
    {
        $subtotal = $this->otServicios()->sum('subtotal');
        $iva = $subtotal * 0.16;
        $total = $subtotal + $iva;

        $this->update([
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ordenes')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}