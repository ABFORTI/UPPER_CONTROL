<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('archivo_excel_stored_name')->nullable()->after('metadata_json');
            $table->string('archivo_excel_nombre_original')->nullable()->after('archivo_excel_stored_name');
            $table->unsignedBigInteger('archivo_excel_subido_por')->nullable()->after('archivo_excel_nombre_original');
            $table->timestamp('archivo_excel_subido_at')->nullable()->after('archivo_excel_subido_por');

            $table->index('archivo_excel_stored_name');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropIndex(['archivo_excel_stored_name']);
            $table->dropColumn([
                'archivo_excel_stored_name',
                'archivo_excel_nombre_original',
                'archivo_excel_subido_por',
                'archivo_excel_subido_at',
            ]);
        });
    }
};
