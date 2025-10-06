<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('cantidad');
            $table->decimal('iva', 12, 2)->default(0)->after('subtotal');
            $table->decimal('total', 12, 2)->default(0)->after('iva');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn(['subtotal','iva','total']);
        });
    }
};
