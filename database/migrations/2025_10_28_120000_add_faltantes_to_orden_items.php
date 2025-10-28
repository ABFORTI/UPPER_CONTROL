<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('orden_items','faltantes')) {
            Schema::table('orden_items', function (Blueprint $table) {
                $table->unsignedInteger('faltantes')->default(0)->after('cantidad_real');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orden_items','faltantes')) {
            Schema::table('orden_items', function (Blueprint $table) {
                $table->dropColumn('faltantes');
            });
        }
    }
};
