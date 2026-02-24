<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->foreignId('parent_ot_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('ordenes_trabajo')
                  ->nullOnDelete();

            $table->unsignedSmallInteger('split_index')
                  ->default(0)
                  ->after('parent_ot_id');

            $table->string('ot_status', 20)
                  ->default('active')
                  ->after('estatus')
                  ->comment('active, partial, closed, canceled');

            $table->index(['parent_ot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropForeign(['parent_ot_id']);
            $table->dropIndex(['parent_ot_id']);
            $table->dropColumn(['parent_ot_id', 'split_index', 'ot_status']);
        });
    }
};
