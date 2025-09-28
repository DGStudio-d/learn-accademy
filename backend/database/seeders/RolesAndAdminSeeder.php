<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $roles = ['student', 'teacher', 'admin'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // Basic permissions (extend later as needed)
        $perms = [
            'view programs', 'attempt quizzes', 'manage quizzes', 'manage meetings', 'manage languages', 'manage settings', 'assign teachers',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Assign core permissions
        Role::findByName('student')->givePermissionTo(['view programs', 'attempt quizzes']);
        Role::findByName('teacher')->givePermissionTo(['view programs', 'manage quizzes', 'manage meetings']);
        Role::findByName('admin')->givePermissionTo($perms);

        // Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@learn-academy.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'notify_email' => true,
                'notify_whatsapp' => false,
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
