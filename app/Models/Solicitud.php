<?php

// app/Models/Solicitud.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\User;

class Solicitud extends Model {
  use LogsActivity;
  protected $table='solicitudes';
  protected $casts = [
    'tamanos_json' => 'array',
    'metadata_json' => 'array',
  ];
  protected $fillable=[
    'folio','id_cliente','id_centrotrabajo','id_servicio',
    'tamano','descripcion','id_area','id_centrocosto','id_marca','cantidad','subtotal','iva','total','notas','estatus','aprobada_por','aprobada_at','tamanos_json',
    'motivo_rechazo',
    'id_cotizacion',
    'id_cotizacion_item',
    'id_cotizacion_item_servicio',
    'metadata_json',
    'archivo_excel_stored_name',
    'archivo_excel_nombre_original',
    'archivo_excel_subido_por',
    'archivo_excel_subido_at'
  ];
  public function cliente(){ return $this->belongsTo(User::class,'id_cliente'); }
  public function centro(){ return $this->belongsTo(CentroTrabajo::class,'id_centrotrabajo'); }
  public function servicio(){ return $this->belongsTo(ServicioEmpresa::class,'id_servicio'); }
  public function area(){ return $this->belongsTo(Area::class,'id_area'); }
  public function centroCosto(){ return $this->belongsTo(CentroCosto::class,'id_centrocosto'); }
  public function marca(){ return $this->belongsTo(Marca::class,'id_marca'); }
  public function archivos(){ return $this->morphMany(\App\Models\Archivo::class,'fileable'); }

  public function archivoExcelSubidoPor(){ return $this->belongsTo(User::class,'archivo_excel_subido_por'); }

  public function getActivitylogOptions(): LogOptions
  {
      return LogOptions::defaults()
          ->useLogName('solicitudes')
          ->logFillable()
          ->logOnlyDirty()
          ->dontSubmitEmptyLogs();
  }
  public function tamanos()
  {
      return $this->hasMany(\App\Models\SolicitudTamano::class, 'id_solicitud');
  }

  public function ordenes()
  {
      return $this->hasMany(\App\Models\Orden::class, 'id_solicitud');
  }

  // NUEVO: Relación con múltiples servicios
  public function servicios()
  {
      return $this->hasMany(\App\Models\SolicitudServicio::class, 'solicitud_id');
  }

  // Método helper para recalcular totales desde servicios
  public function recalcularTotales()
  {
      $subtotal = $this->servicios->sum('subtotal');
      $iva = $subtotal * 0.16;
      $total = $subtotal + $iva;
      
      $this->update([
          'subtotal' => $subtotal,
          'iva' => $iva,
          'total' => $total,
      ]);
  }
}
