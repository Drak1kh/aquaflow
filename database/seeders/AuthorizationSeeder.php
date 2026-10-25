<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AuthorizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $codes = [
            'consumers.view', 'consumers.manage', 'connections.view', 'connections.manage',
            'readings.manage', 'rates.manage', 'periods.manage', 'bills.view',
            'bills.generate', 'bills.adjust', 'payments.view', 'payments.record',
            'payments.verify', 'service.manage', 'reports.view', 'staff.manage',
            'provider.manage', 'support.manage',
        ];

        foreach ($codes as $code) {
            Permission::firstOrCreate(['code' => $code], ['name' => str_replace('.', ' ', ucfirst($code))]);
        }

        $manager = Role::firstOrCreate(['slug' => 'provider_manager'], ['name' => 'Provider Manager']);
        $clerk = Role::firstOrCreate(['slug' => 'provider_clerk'], ['name' => 'Provider Clerk']);
        if ($manager->wasRecentlyCreated) {
            $manager->permissions()->sync(Permission::pluck('id'));
        }
        if ($clerk->wasRecentlyCreated) {
            $clerk->permissions()->sync(Permission::whereIn('code', [
                'consumers.view', 'connections.view', 'bills.view', 'payments.view', 'payments.record', 'reports.view',
            ])->pluck('id'));
        }
    }
}
