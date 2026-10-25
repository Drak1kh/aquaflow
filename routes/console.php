<?php

use App\Actions\BillingService;
use App\Models\Bill;
use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:assign-role {email} {role}', function (string $email, string $role): int {
    $userRole = UserRole::tryFrom($role);

    if ($userRole === null) {
        $this->error('Invalid role. Use super_admin, water_provider, or consumer.');

        return Command::FAILURE;
    }

    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error('User not found.');

        return Command::FAILURE;
    }

    $user->role = $userRole;
    $user->save();
    $this->info("Assigned {$userRole->label()} to {$user->email}.");

    return Command::SUCCESS;
})->purpose('Assign a role to an existing user');

Artisan::command('bills:apply-late-fees', function (BillingService $billing): int {
    Bill::whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
        ->whereNull('late_fee_applied_at')->whereDate('due_at', '<', today())
        ->chunkById(100, function ($bills) use ($billing): void {
            foreach ($bills as $bill) {
                $billing->applyLateFee($bill);
            }
        });

    $this->info('Overdue bills checked.');

    return Command::SUCCESS;
})->purpose('Apply one late fee to eligible overdue bills');

Schedule::command('bills:apply-late-fees')->dailyAt('00:15');
