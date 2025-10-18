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
        'motivo_rechazo','acciones_correctivas'
    ];
    public function solicitud(){ return $this->belongsTo(Solicitud::class,'id_solicitud'); }
    public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
    public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
    public function area(){ return $this->belongsTo(Area::class,'id_area'); }
    public function items(){ return $this->hasMany(OrdenItem::class,'id_orden'); }
    public function avances(){ return $this->hasMany(Avance::class,'id_orden'); }
    public function teamLeader(){ return $this->belongsTo(User::class,'team_leader_id'); }
    public function evidencias(){ return $this->hasMany(\App\Models\Evidencia::class,'id_orden'); }
    public function aprobaciones()
  
{
    return $this->morphMany(\App\Models\Aprobacion::class, 'aprobable');
}

    public function factura(){ return $this->hasOne(\App\Models\Factura::class,'id_orden'); }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ordenes')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}