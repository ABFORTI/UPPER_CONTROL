<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->updateOrInsert(
            ['key' => 'revision_cliente_no_autoriza'],
            [
                'nombre' => 'Revisión cuando cliente no autoriza',
                'descripcion' => 'Permite al cliente solicitar revisión con comentario y fotos para que coordinación revise por qué no autoriza una OT.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('features')
            ->where('key', 'revision_cliente_no_autoriza')
            ->delete();
    }
};
