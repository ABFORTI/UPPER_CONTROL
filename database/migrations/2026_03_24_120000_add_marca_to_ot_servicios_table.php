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
        Schema::table('ot_servicios', function (Blueprint $table) {
            if (!Schema::hasColumn('ot_servicios', 'marca')) {
                $table->string('marca', 255)->nullable()->after('pedimento');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicios', function (Blueprint $table) {
            if (Schema::hasColumn('ot_servicios', 'marca')) {
                $table->dropColumn('marca');
            }
        });
    }
};
