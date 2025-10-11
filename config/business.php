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
    'ot_autorizacion_timeout_minutos' => env('OT_AUTORIZACION_TIMEOUT_MINUTOS', 1),

    // Si true, aplica el bloqueo de solicitudes por OTs vencidas sin autorizar
    'bloquear_solicitudes_por_ots_vencidas' => env('BLOQUEAR_SOLICITUDES_OTS_VENCIDAS', true),
];
