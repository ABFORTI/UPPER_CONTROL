<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('avances')) {
            return;
        }

        Schema::table('avances', function (Blueprint $table) {
            if (!Schema::hasColumn('avances', 'tipo')) {
                $table->string('tipo', 60)->nullable()->after('cantidad');
                $table->index(['id_orden', 'tipo']);
            }

            if (!Schema::hasColumn('avances', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id_usuario')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('avances', 'user_id') && Schema::hasColumn('avances', 'id_usuario')) {
            DB::table('avances')
                ->whereNull('user_id')
                ->whereNotNull('id_usuario')
                ->update(['user_id' => DB::raw('id_usuario')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('avances')) {
            return;
        }

        Schema::table('avances', function (Blueprint $table) {
            if (Schema::hasColumn('avances', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            if (Schema::hasColumn('avances', 'tipo')) {
                $table->dropIndex(['id_orden', 'tipo']);
                $table->dropColumn('tipo');
            }
        });
    }
};
