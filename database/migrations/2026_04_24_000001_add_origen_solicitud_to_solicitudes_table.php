<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo origen_solicitud a la tabla solicitudes.
 *
 * Valores:
 *   - manual          → creada por un usuario humano desde la interfaz
 *   - python_etiquetas → creada automáticamente por el sistema de etiquetas Python
 *   - excel            → importada desde archivo Excel
 *   - api              → creada por cualquier integración API genérica
 *
 * Se introduce para que el rol Cliente_Autorizador_Integraciones pueda ver
 * y autorizar solicitudes generadas por la integración Python aunque no las
 * haya creado directamente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            // Columna de cadena simple — más fácil de extender que un ENUM en MySQL
            $table->string('origen_solicitud', 30)
                ->default('manual')
                ->after('es_integracion_etiquetas')
                ->comment('Origen de la solicitud: manual | python_etiquetas | excel | api');

            $table->index('origen_solicitud', 'idx_sol_origen_solicitud');
        });

        // Retroalimentar solicitudes existentes: las de integración etiquetas → python_etiquetas
        \Illuminate\Support\Facades\DB::statement("
            UPDATE solicitudes
            SET origen_solicitud = 'python_etiquetas'
            WHERE es_integracion_etiquetas = 1
              AND origen_solicitud = 'manual'
        ");
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropIndex('idx_sol_origen_solicitud');
            $table->dropColumn('origen_solicitud');
        });
    }
};
