<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = [
        'key',
        'nombre',
        'descripcion',
    ];

    /**
     * Centros donde esta feature existe (habilitada o no).
     */
    public function centrosTrabajo(): BelongsToMany
    {
        return $this->belongsToMany(CentroTrabajo::class, 'centro_feature')
            ->withPivot(['enabled']);
    }
}
