<?php

// app/Models/Evidencia.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Evidencia extends Model
{
  protected $table = 'evidencias';
  protected $fillable = [
    'id_orden','id_item','id_avance','id_usuario','path','original_name','mime','size'
  ];

  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
  public function item(){ return $this->belongsTo(OrdenItem::class,'id_item'); }
  public function usuario(){ return $this->belongsTo(User::class,'id_usuario'); }

  // URL pública del archivo
  protected $appends = ['url'];
  public function getUrlAttribute(): string
  {
    // Usar el helper oficial de Laravel para generar URL del disco.
    // Respeta APP_URL/ASSET_URL y el modo "serve" sin necesidad de symlink.
    return Storage::disk('public')->url((string)$this->path);
  }
}
