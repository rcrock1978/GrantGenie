<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * T023: Seed the four tenant-scoped roles required by FR-018.
 *
 * The role table is referenced by the spatie/laravel-permission package, but
 * we use a small static `roles` table with explicit integer IDs (see migration
 * 2026_07_03_000002). The seeder ensures the four rows are present and
 * registered with spatie's permission cache.
 */
final class RoleSeeder extends Seeder
{
    public const ADMIN = 1;
    public const WRITER = 2;
    public const REVIEWER = 3;
    public const VIEWER = 4;

    public const ROLES = [
        self::ADMIN => 'admin',
        self::WRITER => 'writer',
        self::REVIEWER => 'reviewer',
        self::VIEWER => 'viewer',
    ];

    public function run(): void
    {
        DB::table('roles')->upsert(
            array_map(
                static fn (int $id, string $name): array => [
                    'id' => $id,
                    'name' => $name,
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                array_keys(self::ROLES),
                array_values(self::ROLES),
            ),
            ['id'],
            ['name', 'guard_name', 'updated_at'],
        );

        // Register with spatie/laravel-permission (in-memory) so the package's
        // Role::findByName() works without an extra DB row.
        foreach (self::ROLES as $id => $name) {
            $role = Role::firstOrNew(['name' => $name, 'guard_name' => 'api']);
            $role->save();
        }
    }
}
