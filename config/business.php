<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reglas de Negocio
    |--------------------------------------------------------------------------
    |
    | Configuraciones de lógica de negocio específicas de Upper Control.
    |
    */

    // Tiempo máximo (en minutos) para que un cliente autorice una OT completada
    // antes de bloquear nuevas solicitudes en ese centro.
    //
    // PARA PRUEBAS: 1 (1 minuto)
    // PARA PRODUCCIÓN: 4320 (72 horas = 3 días)
    'ot_autorizacion_timeout_minutos' => env('OT_AUTORIZACION_TIMEOUT_MINUTOS', 4320),

    // Si true, aplica el bloqueo de solicitudes por OTs vencidas sin autorizar
    'bloquear_solicitudes_por_ots_vencidas' => env('BLOQUEAR_SOLICITUDES_OTS_VENCIDAS', true),

    // Intervalo de tiempo (en minutos) entre recordatorios de validación de OT
    // para clientes que tienen órdenes completadas y validadas por calidad
    // pero pendientes de autorización.
    //
    // PARA PRUEBAS: 1 (1 minuto)
    // PARA PRODUCCIÓN: 360 (6 horas)
    'recordatorio_validacion_intervalo_minutos' => env('RECORDATORIO_VALIDACION_INTERVALO', 360),

    // Si true, envía email al coordinador cuando un cliente autoriza una cotización.
    // Siempre se genera notificación in-app (campanita) vía canal database.
    'notify_coordinator_email_on_quotation_approved' => env('NOTIFY_COORDINATOR_EMAIL_ON_QUOTATION_APPROVED', false),
];
