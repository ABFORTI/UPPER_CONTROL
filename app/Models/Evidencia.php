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

  // URL pública del archivo
  protected $appends = ['url'];
  public function getUrlAttribute(): string
  {
    // Normalizar el path almacenado y construir una URL que apunte
    // a la ruta interna protegida encargada de servir archivos del disco
    // `public`. De esta forma evitamos que prefijos como `app/public/`
    // (comunes en hostings compartidos) terminen generando rutas inválidas.
    $raw = (string) $this->path;
    // Quitar un posible prefijo `app/public/` o `/app/public/`
    $normalized = preg_replace('#^/?app/public/#', '', $raw);
    // Asegurar que no tenemos prefijos redundantes
    $normalized = ltrim($normalized, '/');

    // Construir la URL usando la ruta nombrada `storage.serve`
    // (definida como `/secure-files/{path}`) para evitar conflictos
    // con carpetas físicas como `public/storage`.
    return route('storage.serve', ['path' => $normalized]);
  }
}
