<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;

class PlatformUserSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'platform-admin@helpdesk.test';

    public const DEFAULT_PASSWORD = 'PlatformAdmin123!';

    public function run(): void
    {
        $user = PlatformUser::query()->updateOrCreate(
            ['email' => self::DEFAULT_EMAIL],
            [
                'name' => 'Platform Admin',
                'password' => self::DEFAULT_PASSWORD,
                'is_active' => true,
            ],
        );

        $user->syncRoles(['super_admin']);
    }
}
