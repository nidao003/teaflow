<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /**
         * 创建用户帐号
         * 1.判断帐号是否存在
         * 2.创建帐号
         */
        $email = 'sunkangchina@163.com';
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
               'name' => 'ken',
               'email' => $email,
               'password' => password_hash('111111', PASSWORD_DEFAULT),
               'type' => 'admin',
            ]);
        }
    }
}
