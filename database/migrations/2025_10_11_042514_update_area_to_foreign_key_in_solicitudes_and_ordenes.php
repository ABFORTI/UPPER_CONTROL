<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar columna area (VARCHAR) de solicitudes
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn('area');
        });
        
        // Eliminar columna area (VARCHAR) de ordenes_trabajo
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('area');
        });
        
        // Agregar id_area como foreign key en solicitudes
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->foreignId('id_area')->nullable()->after('descripcion')->constrained('areas')->nullOnDelete();
        });
        
        // Agregar id_area como foreign key en ordenes_trabajo
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->foreignId('id_area')->nullable()->after('id_servicio')->constrained('areas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropForeign(['id_area']);
            $table->dropColumn('id_area');
        });
        
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropForeign(['id_area']);
            $table->dropColumn('id_area');
        });
        
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('area')->nullable()->after('descripcion');
        });
        
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('area')->nullable()->after('id_servicio');
        });
    }
};
