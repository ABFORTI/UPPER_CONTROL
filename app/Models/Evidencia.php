<?php

// app/Models/Evidencia.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evidencia extends Model
{
  protected $table = 'evidencias';
  protected $fillable = [
    'id_orden','id_item','id_avance','id_usuario','path','original_name','mime','size'
  ];

  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
  public function item(){ return $this->belongsTo(OrdenItem::class,'id_item'); }
  public function usuario(){ return $this->belongsTo(User::class,'id_usuario'); }
}
