<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'origen_integracion')) {
                $table->string('origen_integracion')->nullable()->after('pedimento');
                $table->index('origen_integracion');
            }

            if (!Schema::hasColumn('solicitudes', 'referencia_externa')) {
                $table->string('referencia_externa')->nullable()->after('origen_integracion');
                $table->index('referencia_externa');
            }

            if (!Schema::hasColumn('solicitudes', 'paqueteria')) {
                $table->string('paqueteria')->nullable()->after('referencia_externa');
            }

            if (!Schema::hasColumn('solicitudes', 'numero_factura')) {
                $table->string('numero_factura')->nullable()->after('paqueteria');
            }

            if (!Schema::hasColumn('solicitudes', 'numero_cajas')) {
                $table->integer('numero_cajas')->nullable()->after('numero_factura');
            }

            if (!Schema::hasColumn('solicitudes', 'pedido')) {
                $table->string('pedido')->nullable()->after('numero_cajas');
            }

            if (!Schema::hasColumn('solicitudes', 'es_integracion_etiquetas')) {
                $table->boolean('es_integracion_etiquetas')->default(false)->after('pedido');
                $table->index('es_integracion_etiquetas');
            }

            if (!Schema::hasColumn('solicitudes', 'solicitante_externo')) {
                $table->string('solicitante_externo')->nullable()->after('es_integracion_etiquetas');
            }

            // Unicidad segura por origen + referencia para evitar choques entre sistemas externos distintos.
            if (Schema::hasColumn('solicitudes', 'origen_integracion') && Schema::hasColumn('solicitudes', 'referencia_externa')) {
                $table->unique(['origen_integracion', 'referencia_externa'], 'solicitudes_origen_ref_ext_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            try { $table->dropUnique('solicitudes_origen_ref_ext_unique'); } catch (\Throwable $e) {}
            try { $table->dropIndex(['origen_integracion']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['referencia_externa']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['es_integracion_etiquetas']); } catch (\Throwable $e) {}

            $columns = [
                'origen_integracion',
                'referencia_externa',
                'paqueteria',
                'numero_factura',
                'numero_cajas',
                'pedido',
                'es_integracion_etiquetas',
                'solicitante_externo',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('solicitudes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
