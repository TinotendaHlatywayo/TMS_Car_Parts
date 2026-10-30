<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $platform = Platform::firstOrCreate(
            ['slug' => 'tms-rusape'],
            [
                'name' => 'Taziva Motor Spares Platform',
                'currency_code' => 'USD',
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'TMS-01'],
            [
                'platform_id' => $platform->id,
                'name' => 'TMS Rusape Main Branch',
                'slug' => 'tms-rusape-main',
                'timezone' => 'Africa/Harare',
                'currency_code' => 'USD',
            ]
        );

        $email = config('seeding.admin_email');
        $password = config('seeding.admin_password');

        if (! $password && app()->environment('production')) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must be set before seeding production.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password ?: 'admin123'),
                'is_active' => true,
                'is_platform_admin' => true,
            ]
        );

        $user->forceFill([
            'is_active' => true,
            'is_platform_admin' => true,
        ])->save();

        BranchUser::firstOrCreate(
            ['branch_id' => $branch->id, 'user_id' => $user->id],
            [
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
