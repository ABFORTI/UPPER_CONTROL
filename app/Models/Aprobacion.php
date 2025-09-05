<?php

// app/Models/Aprobacion.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Aprobacion extends Model {
  protected $table='aprobaciones';
  protected $fillable = [
        'aprobable_type', 'aprobable_id', // <- ¡IMPORTANTE!
        'tipo', 'resultado', 'observaciones', 'id_usuario'
    ];
  public function aprobable(){ return $this->morphTo(); }
  public function usuario(){ return $this->belongsTo(User::class,'id_usuario'); }
}
