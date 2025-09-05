<?php

// app/Models/ServicioCentro.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServicioCentro extends Model {
  protected $table = 'servicios_centro';
  protected $fillable = ['id_centrotrabajo','id_servicio','precio_base'];
  public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
  public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
  public function tamanos(){ return $this->hasMany(ServicioTamano::class,'id_servicio_centro'); }
}
