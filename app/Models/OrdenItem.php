<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OrdenItem extends Model {
  protected $table='orden_items';
  protected $fillable=['id_orden','descripcion','tamano','cantidad_planeada','cantidad_real','faltantes','precio_unitario','subtotal','sku','marca'];
  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
  public function evidencias(){ return $this->hasMany(\App\Models\Evidencia::class,'id_item'); }
  public function segmentosProduccion(){
    return $this->hasMany(\App\Models\OrdenItemProduccionSegmento::class,'id_item');
  }
}