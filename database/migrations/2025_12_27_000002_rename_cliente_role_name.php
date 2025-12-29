<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'cliente')
            ->where('guard_name', 'web')
            ->update(['name' => 'supervisor']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'supervisor')
            ->where('guard_name', 'web')
            ->update(['name' => 'cliente']);
    }
};
