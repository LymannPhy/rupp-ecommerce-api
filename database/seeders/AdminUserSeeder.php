<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure "admin" role exists
        $adminRole = Role::where('name', 'admin')->first();

        if (!$adminRole) {
            $adminRole = Role::create(['name' => 'admin']);
        }

        // Create admin user
        $admin = User::firstOrCreate([
            'email' => 'admin@gmail.com'
        ], [
            'uuid' => Str::uuid()->toString(),
            'name' => 'Super Admin',
            'password' => Hash::make('password123'), 
            'is_verified' => true, 
        ]);

        // Attach admin role
        $admin->roles()->sync([$adminRole->id]);

        echo "✅ Admin user created: admin@example.com | Password: password123\n";
    }
}
