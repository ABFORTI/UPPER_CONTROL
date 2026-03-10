<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('servicios_empresa') || !Schema::hasTable('servicios_centro')) {
            return;
        }

        // 1) Remueve unicidad global por nombre (si existe)
        Schema::table('servicios_empresa', function (Blueprint $table) {
            try {
                $table->dropUnique('servicios_empresa_nombre_unique');
            } catch (\Throwable $e) {
                // No-op: puede no existir en algunos entornos
            }
        });

        // 2) Agrega campos por centro para nombre y configuracion de captura
        Schema::table('servicios_centro', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios_centro', 'nombre')) {
                $table->string('nombre', 150)->nullable()->after('id_servicio');
            }

            if (!Schema::hasColumn('servicios_centro', 'usa_tamanos')) {
                $table->boolean('usa_tamanos')->default(false)->after('nombre');
            }
        });

        // 3) Backfill de nombre por centro
        $scRows = DB::table('servicios_centro')->select('id', 'id_servicio')->get();
        foreach ($scRows as $row) {
            $nombre = DB::table('servicios_empresa')->where('id', $row->id_servicio)->value('nombre');
            if ($nombre !== null) {
                DB::table('servicios_centro')->where('id', $row->id)->update(['nombre' => trim((string) $nombre)]);
            }
        }

        // 4) Backfill de usa_tamanos por centro (primero desde servicio global)
        $scRows = DB::table('servicios_centro')->select('id', 'id_servicio')->get();
        foreach ($scRows as $row) {
            $usa = (bool) DB::table('servicios_empresa')->where('id', $row->id_servicio)->value('usa_tamanos');
            DB::table('servicios_centro')->where('id', $row->id)->update(['usa_tamanos' => $usa]);
        }

        // 5) Si tiene registros en servicio_tamanos, forzar usa_tamanos=true para ese centro/servicio
        $idsConTamanos = DB::table('servicio_tamanos')->distinct()->pluck('id_servicio_centro');
        if ($idsConTamanos->isNotEmpty()) {
            DB::table('servicios_centro')->whereIn('id', $idsConTamanos->all())->update(['usa_tamanos' => true]);
        }

        // 6) Saneamiento de duplicados historicos dentro del mismo centro por nombre
        $dupes = DB::table('servicios_centro')
            ->select('id_centrotrabajo', DB::raw('LOWER(TRIM(nombre)) as nombre_norm'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('nombre')
            ->groupBy('id_centrotrabajo', DB::raw('LOWER(TRIM(nombre))'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            $rows = DB::table('servicios_centro')
                ->where('id_centrotrabajo', $dupe->id_centrotrabajo)
                ->whereRaw('LOWER(TRIM(nombre)) = ?', [$dupe->nombre_norm])
                ->orderBy('id')
                ->get(['id', 'nombre']);

            $first = true;
            foreach ($rows as $r) {
                if ($first) {
                    $first = false;
                    continue;
                }

                $nuevoNombre = trim((string) $r->nombre) . ' #' . $r->id;
                DB::table('servicios_centro')->where('id', $r->id)->update(['nombre' => $nuevoNombre]);
            }
        }

        // 7) Unicidad final por centro + nombre
        Schema::table('servicios_centro', function (Blueprint $table) {
            try {
                $table->unique(['id_centrotrabajo', 'nombre'], 'servicios_centro_centro_nombre_unique');
            } catch (\Throwable $e) {
                // No-op: si ya existe en algun entorno
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servicios_empresa') || !Schema::hasTable('servicios_centro')) {
            return;
        }

        Schema::table('servicios_centro', function (Blueprint $table) {
            try {
                $table->dropUnique('servicios_centro_centro_nombre_unique');
            } catch (\Throwable $e) {
                // No-op
            }

            if (Schema::hasColumn('servicios_centro', 'usa_tamanos')) {
                $table->dropColumn('usa_tamanos');
            }
            if (Schema::hasColumn('servicios_centro', 'nombre')) {
                $table->dropColumn('nombre');
            }
        });

        Schema::table('servicios_empresa', function (Blueprint $table) {
            try {
                $table->unique('nombre', 'servicios_empresa_nombre_unique');
            } catch (\Throwable $e) {
                // No-op: puede fallar si ya existen duplicados permitidos por el nuevo esquema
            }
        });
    }
};
