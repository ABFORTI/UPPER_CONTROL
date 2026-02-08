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
            $table->enum('origen', ['SOLICITADO', 'ADICIONAL'])->default('SOLICITADO')->after('subtotal');
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('origen');
            $table->text('nota')->nullable()->after('added_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_servicios', function (Blueprint $table) {
            $table->dropForeign(['added_by_user_id']);
            $table->dropColumn(['origen', 'added_by_user_id', 'nota']);
        });
    }
};
