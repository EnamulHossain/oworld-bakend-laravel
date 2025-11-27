<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class DefaultUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            $defaults = [
                [
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'password' => 'password123',
                    'role' => 'admin',
                    'organization_name' => null,
                    'business_type' => null,
                    'phone' => '1234567890',
                ],
                [
                    'username' => 'orguser',
                    'email' => 'org@example.com',
                    'password' => 'password123',
                    'role' => 'organization',
                    'organization_name' => 'Sample Org',
                    'business_type' => 'Events',
                    'phone' => '555-0101',
                ],
                [
                    'username' => 'member',
                    'email' => 'user@example.com',
                    'password' => 'password123',
                    'role' => 'user',
                    'organization_name' => null,
                    'business_type' => null,
                    'phone' => '555-0202',
                ],
            ];

            foreach ($defaults as $data) {
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'username' => $data['username'],
                        'password' => Hash::make($data['password']),
                        'role' => $data['role'],
                        'organization_name' => $data['organization_name'],
                        'business_type' => $data['business_type'],
                        'phone' => $data['phone'],
                    ]
                );

                Role::firstOrCreate(['name' => $data['role'], 'guard_name' => 'sanctum']);
                $user->syncRoles([$data['role']]);
            }
    }
}
