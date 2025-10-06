<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/SolicitudTamano.php
class SolicitudTamano extends Model
{
    protected $table = 'solicitud_tamanos';

    protected $fillable = ['id_solicitud','tamano','cantidad'];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }
}
