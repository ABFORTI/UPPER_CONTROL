<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_item_servicios', function (Blueprint $table) {
            // qty decimal (equivalente a cantidad). Para no romper lo existente, agregamos qty y lo dejamos en null.
            if (!Schema::hasColumn('cotizacion_item_servicios', 'qty')) {
                $table->decimal('qty', 12, 3)->nullable()->after('cantidad');
            }

            if (!Schema::hasColumn('cotizacion_item_servicios', 'notes')) {
                $table->text('notes')->nullable()->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_item_servicios', function (Blueprint $table) {
            if (Schema::hasColumn('cotizacion_item_servicios', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('cotizacion_item_servicios', 'qty')) {
                $table->dropColumn('qty');
            }
        });
    }
};
