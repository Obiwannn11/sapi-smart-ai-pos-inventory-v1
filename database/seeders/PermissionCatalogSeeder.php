<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Permission katalog bersifat global (tanpa tenant). Set team-id null
        // agar findOrCreate tak mengikatnya ke tenant manapun.
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        // Sumber katalog: config/rbac.php (single source of truth).
        foreach (array_keys(config('rbac.modules')) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $registrar->setPermissionsTeamId($previousTeamId);
        $registrar->forgetCachedPermissions();
    }
}
