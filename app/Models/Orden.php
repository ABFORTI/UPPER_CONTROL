<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Orden extends Model {
    use LogsActivity;
    protected $table='ordenes_trabajo';
    protected $fillable = [
        'id_solicitud','id_centrotrabajo','id_servicio','team_leader_id',
        'estatus','calidad_resultado','total_planeado','total_real'
    ];
    public function solicitud(){ return $this->belongsTo(Solicitud::class,'id_solicitud'); }
    public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
    public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
    public function items(){ return $this->hasMany(OrdenItem::class,'id_orden'); }
    public function avances(){ return $this->hasMany(Avance::class,'id_orden'); }
    public function teamLeader(){ return $this->belongsTo(User::class,'team_leader_id'); }
    public function evidencias(){ return $this->hasMany(\App\Models\Evidencia::class,'id_orden'); }
    public function aprobaciones()
  
{
    return $this->morphMany(\App\Models\Aprobacion::class, 'aprobable');
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