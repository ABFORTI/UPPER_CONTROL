<?php

// app/Models/ServicioEmpresa.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServicioEmpresa extends Model {
  protected $table = 'servicios_empresa';
  protected $fillable = ['nombre','usa_tamanos','activo'];
  // Asegura que Inertia/JSON envíe un boolean real y no cadenas "0"/"1",
  // evitando que en el frontend (Vue) se evalúe "0" como truthy.
  protected $casts = [
    'usa_tamanos' => 'boolean',
  ];
  public function porCentro(){ return $this->hasMany(ServicioCentro::class,'id_servicio'); }
}



