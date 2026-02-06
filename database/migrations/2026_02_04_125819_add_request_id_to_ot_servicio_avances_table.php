<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega columna request_id para idempotencia.
     * Previene duplicados usando el ID único enviado desde frontend.
     */
    public function up(): void
    {
        Schema::table('ot_servicio_avances', function (Blueprint $table) {
            $table->string('request_id', 100)->nullable()->after('created_by');
            
            // Índice único compuesto: un request_id solo puede crear un avance por servicio
            $table->unique(['ot_servicio_id', 'request_id'], 'uk_servicio_request');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicio_avances', function (Blueprint $table) {
            $table->dropUnique('uk_servicio_request');
            $table->dropColumn('request_id');
        });
    }
};
