<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/ServicioTamano.php
class ServicioTamano extends Model
{
    protected $table = 'servicio_tamanos';

    protected $fillable = ['id_servicio_centro','tamano','precio'];

    public function servicioCentro()
    {
        return $this->belongsTo(ServicioCentro::class, 'id_servicio_centro');
    }
}
