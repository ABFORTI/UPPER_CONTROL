<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkuServicio extends Model
{
    protected $table = 'sku_servicios';
    protected $fillable = ['id_centrotrabajo', 'sku', 'id_servicio', 'created_by'];

    public function centro()
    {
        return $this->belongsTo(CentroTrabajo::class, 'id_centrotrabajo');
    }

    public function servicio()
    {
        return $this->belongsTo(ServicioEmpresa::class, 'id_servicio');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Busca el servicio asignado a un SKU dentro de un centro (comparación insensible a mayúsculas/espacios) */
    public static function resolverServicioId(int $centroId, string $sku): ?int
    {
        $sku = trim($sku);
        if ($sku === '') return null;

        return static::where('id_centrotrabajo', $centroId)
            ->whereRaw('UPPER(sku) = ?', [mb_strtoupper($sku)])
            ->value('id_servicio');
    }
}
