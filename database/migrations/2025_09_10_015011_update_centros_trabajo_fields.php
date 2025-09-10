<?php
// database/migrations/xxxx_xx_xx_xxxxxx_update_centros_trabajo_fields.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('centros_trabajo', function (Blueprint $t) {
            if (!Schema::hasColumn('centros_trabajo','prefijo')) {
                $t->string('prefijo',10)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('centros_trabajo','direccion')) {
                $t->string('direccion',255)->nullable()->after('prefijo');
            }
            if (!Schema::hasColumn('centros_trabajo','activo')) {
                $t->boolean('activo')->default(true)->after('direccion');
            }
        });
    }
    public function down(): void {
        Schema::table('centros_trabajo', function (Blueprint $t) {
            if (Schema::hasColumn('centros_trabajo','activo')) $t->dropColumn('activo');
            if (Schema::hasColumn('centros_trabajo','direccion')) $t->dropColumn('direccion');
            if (Schema::hasColumn('centros_trabajo','prefijo')) $t->dropColumn('prefijo');
        });
    }
};
