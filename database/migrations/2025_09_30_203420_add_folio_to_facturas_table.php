<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('facturas', 'folio')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->string('folio')->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('facturas', 'folio')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->dropColumn('folio');
            });
        }
    }
};
