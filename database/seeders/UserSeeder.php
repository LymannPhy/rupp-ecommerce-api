<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure "user" role exists
        $userRole = Role::where('name', 'user')->first();

        if (!$userRole) {
            $userRole = Role::create(['name' => 'user']);
        }

        // Use Faker to generate random user data
        $faker = Faker::create();

        // Create 99 random users
        foreach (range(1, 99) as $index) {
            $user = User::firstOrCreate([
                'email' => $faker->unique()->safeEmail,
            ], [
                'uuid' => Str::uuid()->toString(),
                'name' => $faker->name,
                'password' => Hash::make('password123'),  // Default password for all users
                'is_verified' => true,
            ]);

            // Attach user role
            $user->roles()->sync([$userRole->id]);
        }

        // Create user "kdeylester" with the specified email and name
        $kdeylesterUser = User::firstOrCreate([
            'email' => 'kdeylester@gmail.com'
        ], [
            'uuid' => Str::uuid()->toString(),
            'name' => 'kdeylester',
            'password' => Hash::make('password123'),  // Default password for the specified user
            'is_verified' => true,
        ]);

        // Attach user role to "kdeylester"
        $kdeylesterUser->roles()->sync([$userRole->id]);

        echo "✅ 100 users created, including user: kdeylester@gmail.com\n";
    }
}
