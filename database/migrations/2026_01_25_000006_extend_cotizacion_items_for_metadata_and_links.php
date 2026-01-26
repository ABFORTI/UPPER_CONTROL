<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizacion_items', 'product_name')) {
                $table->string('product_name')->nullable()->after('descripcion');
            }

            if (!Schema::hasColumn('cotizacion_items', 'quantity')) {
                $table->decimal('quantity', 12, 3)->nullable()->after('cantidad');
            }

            if (!Schema::hasColumn('cotizacion_items', 'unit')) {
                $table->string('unit', 20)->default('pz')->after('quantity');
            }

            // Si existe el catálogo centros_costos en el sistema, permitir referencia opcional
            if (!Schema::hasColumn('cotizacion_items', 'centro_costo_id')) {
                $table->unsignedBigInteger('centro_costo_id')->nullable()->after('unit');
                $table->index(['centro_costo_id']);
            }

            if (!Schema::hasColumn('cotizacion_items', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('centro_costo_id');
                $table->index(['brand_id']);
            }

            if (!Schema::hasColumn('cotizacion_items', 'metadata')) {
                $table->json('metadata')->nullable()->after('notas');
            }
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            // FKs con tolerancia
            try {
                if (Schema::hasColumn('cotizacion_items', 'centro_costo_id')) {
                    $table->foreign('centro_costo_id')->references('id')->on('centros_costos')->nullOnDelete();
                }
            } catch (Throwable $e) {}

            try {
                if (Schema::hasColumn('cotizacion_items', 'brand_id')) {
                    $table->foreign('brand_id')->references('id')->on('marcas')->nullOnDelete();
                }
            } catch (Throwable $e) {}
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            try { $table->dropForeign(['centro_costo_id']); } catch (Throwable $e) {}
            try { $table->dropForeign(['brand_id']); } catch (Throwable $e) {}

            if (Schema::hasColumn('cotizacion_items', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('cotizacion_items', 'brand_id')) {
                try { $table->dropIndex(['brand_id']); } catch (Throwable $e) {}
                $table->dropColumn('brand_id');
            }

            if (Schema::hasColumn('cotizacion_items', 'centro_costo_id')) {
                try { $table->dropIndex(['centro_costo_id']); } catch (Throwable $e) {}
                $table->dropColumn('centro_costo_id');
            }

            if (Schema::hasColumn('cotizacion_items', 'unit')) {
                $table->dropColumn('unit');
            }

            if (Schema::hasColumn('cotizacion_items', 'quantity')) {
                $table->dropColumn('quantity');
            }

            if (Schema::hasColumn('cotizacion_items', 'product_name')) {
                $table->dropColumn('product_name');
            }
        });
    }
};
