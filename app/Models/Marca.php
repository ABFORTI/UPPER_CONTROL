<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $table = 'marcas';

    protected $fillable = [
        'id_centrotrabajo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function centro()
    {
        return $this->belongsTo(CentroTrabajo::class, 'id_centrotrabajo');
    }

    public function scopeActivas($q)
    {
        return $q->where('activo', true);
    }
}
