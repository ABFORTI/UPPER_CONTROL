<?php

// app/Models/Factura.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Factura extends Model {
    use LogsActivity;
    protected $table='facturas';
    protected $fillable = [
        'id_orden','folio','folio_externo','total','estatus','fecha_facturado','fecha_cobro','fecha_pagado','pdf_path','xml_path'
    ];
    public function orden(){ return $this->belongsTo(Orden::class,'id_orden'); }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('facturas')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
