<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('evidencias')) {
            return;
        }

        if (!Schema::hasColumn('evidencias', 'avance_id')) {
            Schema::table('evidencias', function (Blueprint $table) {
                $table->foreignId('avance_id')->nullable()->after('id_item')->constrained('avances')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('evidencias', 'id_avance')) {
            DB::statement('UPDATE evidencias SET avance_id = id_avance WHERE avance_id IS NULL AND id_avance IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('evidencias') || !Schema::hasColumn('evidencias', 'avance_id')) {
            return;
        }

        Schema::table('evidencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avance_id');
        });
    }
};
