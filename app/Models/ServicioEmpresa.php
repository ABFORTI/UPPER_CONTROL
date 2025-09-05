<?php

// app/Models/ServicioEmpresa.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServicioEmpresa extends Model {
  protected $table = 'servicios_empresa';
  protected $fillable = ['nombre','usa_tamanos','activo'];
  public function porCentro(){ return $this->hasMany(ServicioCentro::class,'id_servicio'); }
}



