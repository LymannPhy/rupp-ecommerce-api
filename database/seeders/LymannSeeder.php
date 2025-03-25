<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class LymannSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kdeylesterUser = User::firstOrCreate(
            ['email' => 'lymannphy9@gmail.com'],
            [
                'uuid' => Str::uuid()->toString(),
                'name' => 'Phy Lymann',
                'password' => Hash::make('password123'),
                'is_verified' => true,
            ]
        );

        $this->command->info('Lymann user seeded: ' . $kdeylesterUser->email);
    }
}
