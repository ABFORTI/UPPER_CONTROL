<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ot_ajustes_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('ot_ajustes_detalle', 'ot_detalle_id')) {
                $table->unsignedBigInteger('ot_detalle_id')->nullable()->change();
            }

            if (!Schema::hasColumn('ot_ajustes_detalle', 'orden_item_id')) {
                $table->foreignId('orden_item_id')
                    ->nullable()
                    ->after('ot_detalle_id')
                    ->constrained('orden_items')
                    ->cascadeOnDelete();
                $table->index(['ot_id', 'orden_item_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ot_ajustes_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('ot_ajustes_detalle', 'orden_item_id')) {
                $table->dropConstrainedForeignId('orden_item_id');
            }

            if (Schema::hasColumn('ot_ajustes_detalle', 'ot_detalle_id')) {
                $table->unsignedBigInteger('ot_detalle_id')->nullable(false)->change();
            }
        });
    }
};
