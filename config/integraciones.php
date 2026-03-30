<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Integraciones Externas
    |--------------------------------------------------------------------------
    |
    | Configuracion centralizada para mapear valores de catálogos internos
    | por sistema externo y por sede/canal.
    |
    | Convenciones recomendadas:
    | - Las claves de primer nivel son nombres de integracion (snake_case).
    | - El segundo nivel representa sede/canal/tenant (snake_case).
    | - Los valores de catalogo se guardan por NOMBRE para facilitar lectura.
    |
    */

    'sistema_etiquetas' => [
        'cva_gdl' => [
            'centro_trabajo' => 'CVA GDL',
            'centro_costos' => 'MAYOREO',
            'area' => 'PEDIDOS ESPECIALES',
            'marca' => 'Varias',
            'solicitante' => 'Sistema de etiquetas',
            'origen_integracion' => 'sistema_etiquetas',
        ],

        // Ejemplo para futuras sedes/canales:
        // 'cva_cdmx' => [
        //     'centro_trabajo' => 'CVA CDMX',
        //     'centro_costos' => 'MAYOREO',
        //     'area' => 'PEDIDOS ESPECIALES',
        //     'marca' => 'Varias',
        //     'solicitante' => 'Sistema de etiquetas',
        //     'origen_integracion' => 'sistema_etiquetas',
        // ],
    ],

    // Espacio para futuras integraciones:
    // 'otro_sistema' => [
    //     'sede_x' => [
    //         'centro_trabajo' => '...',
    //         'centro_costos' => '...',
    //         'area' => '...',
    //         'marca' => '...',
    //         'solicitante' => '...',
    //         'origen_integracion' => 'otro_sistema',
    //     ],
    // ],
];
