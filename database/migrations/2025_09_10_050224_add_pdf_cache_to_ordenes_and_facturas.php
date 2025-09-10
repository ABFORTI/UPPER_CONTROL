<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_pdf_cache_to_ordenes_and_facturas.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ordenes_trabajo', function (Blueprint $t) {
            $t->string('pdf_path')->nullable()->after('total_real');
            $t->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
        Schema::table('facturas', function (Blueprint $t) {
            $t->string('pdf_path')->nullable()->after('estatus');
            $t->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
        });
    }
    public function down(): void {
        Schema::table('ordenes_trabajo', fn(Blueprint $t)=> $t->dropColumn(['pdf_path','pdf_generated_at']));
        Schema::table('facturas', fn(Blueprint $t)=> $t->dropColumn(['pdf_path','pdf_generated_at']));
    }
};
