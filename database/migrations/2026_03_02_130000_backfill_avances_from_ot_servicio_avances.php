<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ot_servicio_avances') || !Schema::hasTable('ot_servicios') || !Schema::hasTable('avances')) {
            return;
        }

        DB::table('ot_servicio_avances as msa')
            ->join('ot_servicios as os', 'os.id', '=', 'msa.ot_servicio_id')
            ->select([
                'msa.id',
                'os.ot_id as id_orden',
                'msa.created_by as id_usuario',
                'msa.cantidad_registrada as cantidad',
                'msa.comentario',
                'msa.created_at',
                'msa.updated_at',
            ])
            ->orderBy('msa.id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $marker = '[OT_SERVICIO_AVANCE_ID:' . $row->id . ']';

                    $exists = DB::table('avances')
                        ->where('id_orden', (int)$row->id_orden)
                        ->where('comentario', 'like', $marker . '%')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('avances')->insert([
                        'id_orden' => (int)$row->id_orden,
                        'id_item' => null,
                        'id_usuario' => (int)$row->id_usuario,
                        'cantidad' => (int)$row->cantidad,
                        'comentario' => trim($marker . ' ' . (string)($row->comentario ?? '')),
                        'evidencia_url' => null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('avances')) {
            return;
        }

        DB::table('avances')
            ->where('comentario', 'like', '[OT_SERVICIO_AVANCE_ID:%')
            ->delete();
    }
};
