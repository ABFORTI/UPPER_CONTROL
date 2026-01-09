<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('centros_trabajo', function (Blueprint $table) {
            if (!Schema::hasColumn('centros_trabajo', 'numero_centro')) {
                $table->string('numero_centro', 20)->nullable()->after('nombre');
                $table->unique('numero_centro');
            }
        });
    }

    public function down(): void
    {
        Schema::table('centros_trabajo', function (Blueprint $table) {
            if (Schema::hasColumn('centros_trabajo', 'numero_centro')) {
                $table->dropUnique('centros_trabajo_numero_centro_unique');
                $table->dropColumn('numero_centro');
            }
        });
    }
};
