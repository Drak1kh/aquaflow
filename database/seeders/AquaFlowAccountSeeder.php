<?php

namespace Database\Seeders;

use App\Models\Consumer;
use App\Models\ProviderMembership;
use App\Models\Role;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use App\Models\WaterProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AquaFlowAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo accounts are available only in local and testing environments.');

            return;
        }

        $configuredPassword = config('aquaflow.demo_password');
        $password = is_string($configuredPassword) && $configuredPassword !== ''
            ? $configuredPassword
            : Str::password(20);

        $this->call(AuthorizationSeeder::class);
        $managerRole = Role::where('slug', 'provider_manager')->firstOrFail();

        $this->seedUser('AquaFlow Super Admin', 'admin@aquaflow.test', $password, true);

        foreach ([
            [
                'code' => 'DEMO-NORTH',
                'provider' => 'North District Water',
                'staff_name' => 'North Provider Manager',
                'staff_email' => 'provider.north@aquaflow.test',
                'consumer_name' => 'North Consumer',
                'consumer_email' => 'consumer.north@aquaflow.test',
                'account_number' => 'DEMO-NORTH-001',
                'meter_number' => 'M-DEMO-NORTH-001',
                'connection_number' => 'C-DEMO-NORTH-001',
                'service_address' => '101 North Water Street',
            ],
            [
                'code' => 'DEMO-SOUTH',
                'provider' => 'South District Water',
                'staff_name' => 'South Provider Manager',
                'staff_email' => 'provider.south@aquaflow.test',
                'consumer_name' => 'South Consumer',
                'consumer_email' => 'consumer.south@aquaflow.test',
                'account_number' => 'DEMO-SOUTH-001',
                'meter_number' => 'M-DEMO-SOUTH-001',
                'connection_number' => 'C-DEMO-SOUTH-001',
                'service_address' => '202 South Water Street',
            ],
        ] as $account) {
            $provider = WaterProvider::updateOrCreate(
                ['code' => $account['code']],
                ['name' => $account['provider'], 'status' => 'active'],
            );
            $staff = $this->seedUser($account['staff_name'], $account['staff_email'], $password);
            ProviderMembership::updateOrCreate(
                ['water_provider_id' => $provider->id, 'user_id' => $staff->id],
                ['role_id' => $managerRole->id, 'status' => 'active'],
            );

            $consumerUser = $this->seedUser($account['consumer_name'], $account['consumer_email'], $password);
            $consumer = Consumer::updateOrCreate(
                ['water_provider_id' => $provider->id, 'account_number' => $account['account_number']],
                [
                    'user_id' => $consumerUser->id,
                    'name' => $consumerUser->name,
                    'email' => $consumerUser->email,
                    'service_address' => $account['service_address'],
                    'status' => 'active',
                ],
            );
            $meter = WaterMeter::firstOrCreate(
                ['water_provider_id' => $provider->id, 'meter_number' => $account['meter_number']],
            );
            WaterConnection::updateOrCreate(
                ['water_provider_id' => $provider->id, 'connection_number' => $account['connection_number']],
                [
                    'consumer_id' => $consumer->id,
                    'water_meter_id' => $meter->id,
                    'service_address' => $account['service_address'],
                    'status' => 'active',
                ],
            );
        }

        $this->command?->info('Demo accounts: admin@aquaflow.test, provider.north@aquaflow.test, provider.south@aquaflow.test, consumer.north@aquaflow.test, consumer.south@aquaflow.test');
        $this->command?->info('Demo password for all accounts: '.$password);
    }

    private function seedUser(string $name, string $email, string $password, bool $isSuperAdmin = false): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'status' => 'active',
            'is_super_admin' => $isSuperAdmin,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
