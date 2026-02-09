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
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            // Almacenar información del archivo Excel cargado
            $table->string('archivo_excel_path')->nullable()->after('pdf_generated_at');
            $table->string('archivo_excel_nombre_original')->nullable()->after('archivo_excel_path');
            $table->string('archivo_excel_mime')->nullable()->after('archivo_excel_nombre_original');
            $table->integer('archivo_excel_size')->nullable()->after('archivo_excel_mime');
            $table->foreignId('archivo_excel_subido_por')->nullable()->constrained('users')->nullOnDelete()->after('archivo_excel_size');
            $table->timestamp('archivo_excel_subido_at')->nullable()->after('archivo_excel_subido_por');
            
            $table->index('archivo_excel_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropForeign(['archivo_excel_subido_por']);
            $table->dropIndex(['archivo_excel_path']);
            $table->dropColumn([
                'archivo_excel_path',
                'archivo_excel_nombre_original',
                'archivo_excel_mime',
                'archivo_excel_size',
                'archivo_excel_subido_por',
                'archivo_excel_subido_at'
            ]);
        });
    }
};
