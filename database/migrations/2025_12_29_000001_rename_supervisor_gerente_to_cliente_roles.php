<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function renameOrMergeRole(string $from, string $to, string $guard = 'web'): void
    {
        $old = DB::table('roles')
            ->where('name', $from)
            ->where('guard_name', $guard)
            ->first();

        if (!$old) {
            return;
        }

        $new = DB::table('roles')
            ->where('name', $to)
            ->where('guard_name', $guard)
            ->first();

        // Simple rename (keeps same role id => keeps all relationships and permissions)
        if (!$new) {
            DB::table('roles')->where('id', (int) $old->id)->update(['name' => $to]);
            return;
        }

        // If target already exists, merge old into new to preserve privileges.
        $oldId = (int) $old->id;
        $newId = (int) $new->id;

        // Merge permissions (avoid duplicates)
        $permissionIds = DB::table('role_has_permissions')
            ->where('role_id', $oldId)
            ->pluck('permission_id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->updateOrInsert(
                ['permission_id' => (int) $permissionId, 'role_id' => $newId],
                []
            );
        }

        // Move users/models to new role
        DB::table('model_has_roles')->where('role_id', $oldId)->update(['role_id' => $newId]);

        // Remove old role + relations
        DB::table('role_has_permissions')->where('role_id', $oldId)->delete();
        DB::table('roles')->where('id', $oldId)->delete();
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function up(): void
    {
        DB::transaction(function () {
            $this->renameOrMergeRole('gerente', 'Cliente_Gerente');
            $this->renameOrMergeRole('supervisor', 'Cliente_Supervisor');
        });

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        DB::transaction(function () {
            $this->renameOrMergeRole('Cliente_Gerente', 'gerente');
            $this->renameOrMergeRole('Cliente_Supervisor', 'supervisor');
        });

        $this->forgetPermissionCache();
    }
};
