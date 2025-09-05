<?php

// app/Models/Solicitud.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model {
  protected $table='solicitudes';
  protected $fillable=[
    'folio','id_cliente','id_centrotrabajo','id_servicio',
    'tamano','descripcion','cantidad','notas','estatus','aprobada_por','aprobada_at'
  ];
  public function cliente(){ return $this->belongsTo(User::class,'id_cliente'); }
  public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
  public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
  public function archivos(){ return $this->morphMany(\App\Models\Archivo::class,'fileable'); }
}
