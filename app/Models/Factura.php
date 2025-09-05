<?php

// app/Models/Factura.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model {
  protected $table='facturas';
  protected $fillable=['id_orden','total','folio_externo','estatus','fecha_facturado','fecha_cobro','fecha_pagado'];
  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
}
