<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds exactly the two users this system will ever have (SPEC §3).
 *
 * There is no public registration route, so this seeder is the only way an
 * account comes into existence. Idempotent - safe to re-run on deploy.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Jasper',
                'email' => 'jasper@emailjasper.com',
                'password' => Config::string('xoloff.seed_passwords.jasper'),
            ],
            [
                'name' => 'Stephan',
                'email' => 'stephan@xolution.nl',
                'password' => Config::string('xoloff.seed_passwords.stephan'),
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
