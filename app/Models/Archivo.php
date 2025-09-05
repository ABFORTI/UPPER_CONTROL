<?php

// app/Models/Archivo.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Archivo extends Model {
  protected $table='archivos';
  protected $fillable=['path','mime','size','subtipo'];
  public function fileable(){ return $this->morphTo(); }
}