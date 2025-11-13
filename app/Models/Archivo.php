<?php

// app/Models/Archivo.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Archivo extends Model {
  protected $table='archivos';
  protected $fillable=['path','nombre_original','mime','size','subtipo'];
  public function fileable(){ return $this->morphTo(); }

  protected $appends = ['url'];
  public function getUrlAttribute(): string
  {
    // Unificar con Evidencia: usar Storage::url para respetar APP_URL/serve/CDN
    return \Illuminate\Support\Facades\Storage::disk('public')->url((string)$this->path);
  }
}