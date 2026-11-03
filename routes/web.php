<?php

use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Consumer\TicketController as ConsumerTicketController;
use App\Http\Controllers\MvpController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\Provider\ModuleController;
use App\Http\Controllers\Provider\OperationsController;
use App\Http\Middleware\BindSingleProvider;
use App\UserRole;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'update'])->name('password.change.store');
});

Route::middleware(['auth', 'verified', BindSingleProvider::class])->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route(match (auth()->user()->role) {
            UserRole::SuperAdmin => 'dashboard.provider',
            UserRole::WaterProvider => 'dashboard.provider',
            UserRole::Consumer => 'dashboard.consumer',
        });
    })->name('dashboard');

    Route::redirect('dashboard/admin', '/dashboard/provider')->middleware('can:access-super-admin')->name('dashboard.admin');
    Route::get('dashboard/provider', [OperationsController::class, 'index'])->middleware('can:access-water-provider')->name('dashboard.provider');
    Route::get('dashboard/consumer', [ConsumerTicketController::class, 'index'])->middleware('can:access-consumer')->name('dashboard.consumer');

    Route::prefix('admin')->name('admin.')->middleware('can:access-super-admin')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/status', [AdminUserController::class, 'toggle'])->name('users.status');
        Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset');
        Route::get('billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::get('billing/{bill}', [AdminBillingController::class, 'show'])->name('billing.show');
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/proof', [AdminPaymentController::class, 'proof'])->name('payments.proof');
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/print', [AdminReportController::class, 'print'])->name('reports.print');
        Route::get('reports/csv', [AdminReportController::class, 'csv'])->name('reports.csv');
        Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

    Route::get('management/{section}', [MvpController::class, 'management'])->middleware('can:access-water-provider')->name('management');
    Route::post('provider/billing-periods', [MvpController::class, 'billingPeriod'])->middleware('can:billing.manage')->name('provider.periods.store');
    Route::put('provider/profile', [MvpController::class, 'providerSettings'])->middleware('can:access-super-admin')->name('provider.profile');
    Route::put('provider/consumers/{consumer}', [MvpController::class, 'updateConsumer'])->middleware('can:consumers.manage')->name('provider.consumers.update');
    Route::put('provider/readings/{reading}', [MvpController::class, 'correctReading'])->middleware('can:connections.manage')->name('provider.readings.update');
    Route::get('portal/{section}', [MvpController::class, 'portal'])->middleware('can:access-consumer')->name('portal.section');
    Route::post('portal/payments', [MvpController::class, 'submitPayment'])->middleware('can:access-consumer')->name('portal.payments.store');
    Route::get('documents/bills/{bill}', [MvpController::class, 'bill'])->name('bills.print');
    Route::get('documents/payments/{payment}', [MvpController::class, 'receipt'])->name('payments.print');
    Route::get('documents/payments/{payment}/proof', [MvpController::class, 'proof'])->middleware('can:access-water-provider')->name('payments.proof');

    Route::prefix('provider')->name('provider.')->middleware('can:access-water-provider')->group(function () {
        foreach (['consumers', 'readings', 'billing', 'payments'] as $module) {
            Route::get($module, [OperationsController::class, 'index'])->defaults('module', $module)->name($module.'.index');
        }
        Route::get('balances', [MvpController::class, 'management'])->defaults('section', 'balances')->name('balances.index');
        Route::get('profile', [MvpController::class, 'management'])->defaults('section', 'settings')->name('profile.edit');
        Route::get('service', [ModuleController::class, 'service'])->name('service.index');
        Route::post('service/{connection}', [ModuleController::class, 'serviceAction'])->middleware('can:connections.manage')->name('service.store');
        Route::post('bills/{bill}/penalty', [ModuleController::class, 'penalty'])->middleware('can:billing.manage')->name('bills.penalty');
        Route::post('bills/{bill}/adjustments', [ModuleController::class, 'adjustment'])->middleware('can:access-super-admin')->name('adjustments.store');
        Route::get('reports/billing', [ModuleController::class, 'billingReport'])->name('reports.billing');
        Route::get('reports/consumption', [ModuleController::class, 'consumptionReport'])->name('reports.consumption');
        Route::post('consumers', [OperationsController::class, 'consumer'])->middleware('can:consumers.manage')->name('consumers.store');
        Route::patch('consumers/{consumer}/status', [OperationsController::class, 'consumerStatus'])->middleware('can:consumers.manage')->name('consumers.status');
        Route::post('consumers/{consumer}/portal', [OperationsController::class, 'activatePortal'])->middleware('can:consumers.manage')->name('consumers.portal');
        Route::post('connections', [OperationsController::class, 'connection'])->middleware('can:connections.manage')->name('connections.store');
        Route::post('readings', [OperationsController::class, 'reading'])->middleware('can:connections.manage')->name('readings.store');
        Route::post('rates', [OperationsController::class, 'rate'])->middleware('can:billing.manage')->name('rates.store');
        Route::post('bills', [OperationsController::class, 'bill'])->middleware('can:billing.manage')->name('bills.store');
        Route::post('bills/batch', [OperationsController::class, 'batchBills'])->middleware('can:billing.manage')->name('bills.batch');
        Route::post('payments', [OperationsController::class, 'payment'])->middleware('can:payments.manage')->name('payments.store');
        Route::post('payments/{payment}/verify', [OperationsController::class, 'verifyPayment'])->middleware('can:payments.verify')->name('payments.verify');
        Route::post('payments/{payment}/reject', [OperationsController::class, 'rejectPayment'])->middleware('can:payments.verify')->name('payments.reject');
    });

});

require __DIR__.'/settings.php';
