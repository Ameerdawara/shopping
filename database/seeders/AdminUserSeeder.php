<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tasswek.com'], // البريد الإلكتروني للـ Admin
            [
                'name' => 'Admin User',
                'password' => Hash::make('123456789'), // كلمة المرور الخاصة بك
                'role' => 'admin', // تعيين الـ role ليكون admin
            ]
        );
    }
}
