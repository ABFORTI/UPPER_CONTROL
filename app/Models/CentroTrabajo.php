<?php

// app/Models/CentroTrabajo.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class CentroTrabajo extends Model
{
    protected $table = 'centros_trabajo';
    protected $fillable = ['nombre','numero_centro','prefijo','direccion','activo'];

    public function users(){ return $this->hasMany(User::class, 'centro_trabajo_id'); }

    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'centro_trabajo_user')
            ->withTimestamps();
    }

    public function areas()
    {
        return $this->hasMany(Area::class, 'id_centrotrabajo');
    }

    /**
     * Catálogo de funcionalidades asignadas a este centro.
     * Nota: la tabla pivote se llama `centro_feature` por requerimiento.
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'centro_feature')
            ->withPivot(['enabled']);
    }

    /**
     * Determina si el centro tiene habilitada una funcionalidad por `key`.
     *
     * - No hardcodea IDs.
     * - Soporta agregar nuevas funcionalidades solo insertando filas en `features`.
     */
    public function hasFeature(string $key): bool
    {
        $key = trim($key);
        if ($key == '') return false;

        // Si aún no se han corrido las migraciones de feature flags, asumir deshabilitado.
        if (!Schema::hasTable('features') || !Schema::hasTable('centro_feature')) {
            return false;
        }

        if ($this->relationLoaded('features')) {
            return $this->features
                ->contains(function ($feature) use ($key) {
                    return (string) $feature->key === $key
                        && (bool) ($feature->pivot?->enabled ?? false);
                });
        }

        return $this->features()
            ->where('key', $key)
            ->wherePivot('enabled', true)
            ->exists();
    }
}

