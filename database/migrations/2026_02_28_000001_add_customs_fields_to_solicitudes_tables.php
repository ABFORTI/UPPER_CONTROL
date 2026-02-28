<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('descripcion');
            $table->string('origen')->nullable()->after('sku');
            $table->string('pedimento')->nullable()->after('origen');
        });

        Schema::table('solicitud_servicios', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('servicio_id');
            $table->string('origen')->nullable()->after('sku');
            $table->string('pedimento')->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_servicios', function (Blueprint $table) {
            $table->dropColumn(['sku', 'origen', 'pedimento']);
        });

        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn(['sku', 'origen', 'pedimento']);
        });
    }
};
