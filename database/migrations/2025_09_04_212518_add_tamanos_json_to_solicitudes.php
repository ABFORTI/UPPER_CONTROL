<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/xxxx_add_tamanos_json_to_solicitudes.php
Schema::table('solicitudes', function (Illuminate\Database\Schema\Blueprint $t) {
  $t->json('tamanos_json')->nullable()->after('cantidad');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // database/migrations/xxxx_add_tamanos_json_to_solicitudes.php
Schema::table('solicitudes', function (Illuminate\Database\Schema\Blueprint $t) {
  $t->json('tamanos_json')->nullable()->after('cantidad');
});

    }
};
