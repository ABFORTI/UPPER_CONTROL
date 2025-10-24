<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Agregar columnas como NULL para no romper registros existentes (si no existen)
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'id_centrocosto')) {
                $table->unsignedBigInteger('id_centrocosto')->nullable()->after('id_area');
            }
            if (!Schema::hasColumn('solicitudes', 'id_marca')) {
                $table->unsignedBigInteger('id_marca')->nullable()->after('id_centrocosto');
            }
        });

        // (posponer índices y FKs hasta que hagamos backfill)

        // 3) Backfill: crear un centro de costos "General" por cada centro con solicitudes y asignarlo
        $centrosConSolicitudes = DB::table('solicitudes')
            ->select('id_centrotrabajo')
            ->whereNotNull('id_centrotrabajo')
            ->distinct()
            ->pluck('id_centrotrabajo');

        $defaultMap = [];
        foreach ($centrosConSolicitudes as $cid) {
            // Verificar si ya existe un CC General en este centro
            $cc = DB::table('centros_costos')
                ->where('id_centrotrabajo', $cid)
                ->where('nombre', 'General')
                ->first();

            if (!$cc) {
                $ccId = DB::table('centros_costos')->insertGetId([
                    'id_centrotrabajo' => $cid,
                    'nombre' => 'General',
                    'activo' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $ccId = $cc->id;
            }
            $defaultMap[(int)$cid] = (int)$ccId;

            // Asignar a solicitudes de este centro que aún no tengan id_centrocosto
            DB::table('solicitudes')
                ->where('id_centrotrabajo', $cid)
                ->whereNull('id_centrocosto')
                ->update(['id_centrocosto' => $ccId]);

            // Corregir valores inválidos (que no correspondan a un CC existente de ese centro)
            $validIds = DB::table('centros_costos')->where('id_centrotrabajo', $cid)->pluck('id')->map(fn($v)=>(int)$v)->all();
            if (!empty($validIds)) {
                DB::table('solicitudes')
                    ->where('id_centrotrabajo', $cid)
                    ->whereNotNull('id_centrocosto')
                    ->whereNotIn('id_centrocosto', $validIds)
                    ->update(['id_centrocosto' => $ccId]);
            } else {
                // Si no hay válidos por alguna razón, forzar default
                DB::table('solicitudes')
                    ->where('id_centrotrabajo', $cid)
                    ->update(['id_centrocosto' => $ccId]);
            }
        }

        // 4) Hacer NOT NULL el id_centrocosto ahora que todo tiene valor válido
        try {
            DB::statement('ALTER TABLE `solicitudes` MODIFY `id_centrocosto` BIGINT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {
            // si ya es NOT NULL o el motor no permite modificar sin soltar FK, ignorar
        }

        // 5) Agregar índices y llaves foráneas si no existen aún (después del backfill)
        $db = DB::getDatabaseName();
        $hasFkCC = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('CONSTRAINT_NAME', 'solicitudes_id_centrocosto_foreign')
            ->exists();
        $hasFkMarca = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $db)
            ->where('CONSTRAINT_NAME', 'solicitudes_id_marca_foreign')
            ->exists();
        $hasIdxCC = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'solicitudes')
            ->where('INDEX_NAME', 'solicitudes_id_centrocosto_index')
            ->exists();
        $hasIdxMarca = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $db)
            ->where('TABLE_NAME', 'solicitudes')
            ->where('INDEX_NAME', 'solicitudes_id_marca_index')
            ->exists();

        Schema::table('solicitudes', function (Blueprint $table) use ($hasFkCC, $hasFkMarca, $hasIdxCC, $hasIdxMarca) {
            if (!Schema::hasColumn('solicitudes','id_centrocosto')) return; // safety
            if (!$hasIdxCC) { $table->index(['id_centrocosto']); }
            if (!$hasIdxMarca) { $table->index(['id_marca']); }
            if (!$hasFkCC) {
                try {
                    $table->foreign('id_centrocosto')
                        ->references('id')->on('centros_costos')
                        ->onDelete('restrict');
                } catch (\Throwable $e) {}
            }
            if (!$hasFkMarca) {
                try {
                    $table->foreign('id_marca')
                        ->references('id')->on('marcas')
                        ->onDelete('set null');
                } catch (\Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropForeign(['id_centrocosto']);
            $table->dropForeign(['id_marca']);
            $table->dropColumn(['id_centrocosto','id_marca']);
        });
    }
};
