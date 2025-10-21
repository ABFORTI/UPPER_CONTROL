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
    $base = config('filesystems.disks.public.url') ?: (config('app.url').'/storage');
    return rtrim($base,'/').'/'.ltrim((string)$this->path,'/');
  }
}