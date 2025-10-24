<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_centrocosto')->after('id_area');
            $table->unsignedBigInteger('id_marca')->nullable()->after('id_centrocosto');

            $table->foreign('id_centrocosto')
                ->references('id')->on('centros_costos')
                ->onDelete('restrict');
            $table->foreign('id_marca')
                ->references('id')->on('marcas')
                ->onDelete('set null');

            $table->index(['id_centrocosto']);
            $table->index(['id_marca']);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropForeign(['id_centrocosto']);
            $table->dropForeign(['id_marca']);
            $table->dropColumn(['id_centrocosto','id_marca']);
        });
    }
};
