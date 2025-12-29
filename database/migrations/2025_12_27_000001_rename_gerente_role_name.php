<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'gerente')
            ->where('guard_name', 'web')
            ->update(['name' => 'gerente_upper']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'gerente_upper')
            ->where('guard_name', 'web')
            ->update(['name' => 'gerente']);
    }
};
