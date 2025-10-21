<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Avance extends Model {
  protected $table='avances';
  protected $fillable=['id_orden','id_item','id_usuario','cantidad','comentario','evidencia_url','es_corregido'];
  protected $appends=['isCorregido']; // Serializa el accessor en JSON
  
  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
  public function item(){ return $this->belongsTo(OrdenItem::class,'id_item'); }
  public function usuario(){ return $this->belongsTo(User::class,'id_usuario'); }

  public function getIsCorregidoAttribute() {
    return (bool)($this->es_corregido ?? false);
  }
}