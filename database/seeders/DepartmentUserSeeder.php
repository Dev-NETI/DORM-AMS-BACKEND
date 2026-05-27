<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DepartmentUserSeeder extends Seeder
{
    public function run(): void
    {
        $dept = fn(string $code) => Department::where('code', $code)->first()?->id;

        $defaultPermissions = json_encode([
            // Administration
            // 'departments',
            'units',
            // 'employees',
            // 'users',
            // Fixed Assets
            'room-inventory',
            'fdc-room-inventory',
            'cdc-room-inventory',
            // Cleaning Supplies
            'consumables',
            'inventory-stocks',
        ]);

        $users = [
            [
                'name'          => 'Cathy Villareal',
                'email'         => 'cathy.villareal@neti.com.ph',
                'password'      => Hash::make('password'),
                'user_type'     => 'employee',
                'department_id' => $dept('DOD'),
                'permissions'   => $defaultPermissions,
            ],
            [
                'name'          => 'Maureen Pasia',
                'email'         => 'maureen.pasia@neti.com.ph',
                'password'      => Hash::make('password'),
                'user_type'     => 'employee',
                'department_id' => $dept('DOD'),
                'permissions'   => $defaultPermissions,
            ],
            [
                'name'          => 'Sherwin Roxas',
                'email'         => 'sherwin.roxas@neti.com.ph',
                'password'      => Hash::make('password'),
                'user_type'     => 'employee',
                'department_id' => $dept('DOD'),
                'permissions'   => $defaultPermissions,
            ],

        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
