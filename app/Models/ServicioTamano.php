<?php

// app/Models/ServicioTamano.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServicioTamano extends Model {
  protected $table = 'servicio_tamanos';
  protected $fillable = ['id_servicio_centro','tamano','precio'];
  public $timestamps = false;
  public function servicioCentro(){ return $this->belongsTo(ServicioCentro::class,'id_servicio_centro'); }
}
