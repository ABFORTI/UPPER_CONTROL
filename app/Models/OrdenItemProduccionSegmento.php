<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenItemProduccionSegmento extends Model
{
  protected $table = 'orden_item_produccion_segmentos';

  protected $fillable = [
    'id_orden',
    'id_item',
    'id_usuario',
    'tipo_tarifa',
    'cantidad',
    'precio_unitario',
    'subtotal',
    'nota',
  ];

  public function orden() { return $this->belongsTo(Orden::class, 'id_orden'); }
  public function item() { return $this->belongsTo(OrdenItem::class, 'id_item'); }
  public function usuario() { return $this->belongsTo(User::class, 'id_usuario'); }
}
