<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'noc@neti.com.ph'],
            [
                'name'      => 'System Admin',
                'password'  => Hash::make('password'),
                'user_type' => 'system_administrator',
            ]
        );
    }
}
