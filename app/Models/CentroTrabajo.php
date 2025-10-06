<?php

// app/Models/CentroTrabajo.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentroTrabajo extends Model
{
    protected $table = 'centros_trabajo';
    protected $fillable = ['nombre','prefijo','direccion','activo'];

    public function users(){ return $this->hasMany(User::class, 'centro_trabajo_id'); }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'centro_trabajo_user')
            ->withTimestamps();
    }
}

