<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenItem extends Model {
  protected $table='orden_items';
  protected $fillable=['id_orden','descripcion','tamano','cantidad_planeada','cantidad_real','faltantes','precio_unitario','subtotal','sku','marca'];
  public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
  public function evidencias(){ return $this->hasMany(\App\Models\Evidencia::class,'id_item'); }
  public function segmentosProduccion(){
    return $this->hasMany(\App\Models\OrdenItemProduccionSegmento::class,'id_item');
  }

  public function ajustes(): HasMany
  {
    return $this->hasMany(OtAjusteDetalle::class, 'orden_item_id');
  }

  public function calcularMetricas(): array
  {
    $ajustes = $this->relationLoaded('ajustes') ? $this->ajustes : $this->ajustes()->get();

    $extra = (int) $ajustes->where('tipo', 'extra')->sum('cantidad');
    $faltantesAjuste = (int) $ajustes->where('tipo', 'faltante')->sum('cantidad');
    $faltantesLegacy = (int) ($this->faltantes ?? 0);
    $faltantes = $faltantesLegacy + $faltantesAjuste;

    $solicitado = (int) ($this->cantidad_planeada ?? 0) + $faltantesLegacy;
    $totalCobrable = max(0, $solicitado + $extra - $faltantes);
    $completado = (int) ($this->cantidad_real ?? 0);
    $pendiente = max(0, $totalCobrable - $completado);
    $progreso = $totalCobrable > 0 ? round(($completado / $totalCobrable) * 100, 2) : 0.0;

    return [
      'solicitado' => $solicitado,
      'extra' => $extra,
      'faltantes' => $faltantes,
      'total_cobrable' => $totalCobrable,
      'completado' => $completado,
      'pendiente' => $pendiente,
      'progreso' => $progreso,
    ];
  }
}