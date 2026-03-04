<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->index('deleted_at', 'solicitudes_deleted_at_idx');
        });

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            if (!Schema::hasColumn('ordenes_trabajo', 'deleted_at')) {
                $table->softDeletes();
            }
            $table->index('deleted_at', 'ordenes_trabajo_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropIndex('solicitudes_deleted_at_idx');
            if (Schema::hasColumn('solicitudes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropIndex('ordenes_trabajo_deleted_at_idx');
            if (Schema::hasColumn('ordenes_trabajo', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
