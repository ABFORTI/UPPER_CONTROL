<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (!Schema::hasColumn('facturas', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('pdf_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (Schema::hasColumn('facturas', 'xml_path')) {
                $table->dropColumn('xml_path');
            }
        });
    }
};
