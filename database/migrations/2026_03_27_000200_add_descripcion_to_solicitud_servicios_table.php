<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitud_servicios', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitud_servicios', 'descripcion')) {
                $table->string('descripcion')->nullable()->after('pedimento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_servicios', function (Blueprint $table) {
            if (Schema::hasColumn('solicitud_servicios', 'descripcion')) {
                $table->dropColumn('descripcion');
            }
        });
    }
};
