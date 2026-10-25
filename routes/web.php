<?php

use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Consumer\TicketController as ConsumerTicketController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\Provider\OperationsController;
use App\UserRole;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'update'])->name('password.change.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return redirect()->route(match (auth()->user()->role) {
            UserRole::SuperAdmin => 'dashboard.admin',
            UserRole::WaterProvider => 'dashboard.provider',
            UserRole::Consumer => 'dashboard.consumer',
        });
    })->name('dashboard');

    Route::get('dashboard/admin', AdminDashboardController::class)->middleware('can:access-super-admin')->name('dashboard.admin');
    Route::get('dashboard/provider', [OperationsController::class, 'index'])->middleware('can:access-water-provider')->name('dashboard.provider');
    Route::get('dashboard/consumer', [ConsumerTicketController::class, 'index'])->middleware('can:access-consumer')->name('dashboard.consumer');

    Route::prefix('admin')->name('admin.')->middleware('can:access-super-admin')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/status', [AdminUserController::class, 'toggle'])->name('users.status');
        Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset');
        Route::get('providers', [AdminProviderController::class, 'index'])->name('providers.index');
        Route::post('providers', [AdminProviderController::class, 'store'])->name('providers.store');
        Route::put('providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
        Route::patch('providers/{provider}/status', [AdminProviderController::class, 'toggle'])->name('providers.status');
        Route::get('permissions', [AdminPermissionController::class, 'index'])->name('permissions.index');
        Route::put('permissions', [AdminPermissionController::class, 'update'])->name('permissions.update');
        Route::get('billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::get('billing/{bill}', [AdminBillingController::class, 'show'])->name('billing.show');
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/proof', [AdminPaymentController::class, 'proof'])->name('payments.proof');
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/print', [AdminReportController::class, 'print'])->name('reports.print');
        Route::get('reports/csv', [AdminReportController::class, 'csv'])->name('reports.csv');
        Route::get('tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
        Route::put('tickets/{ticket}', [AdminTicketController::class, 'update'])->name('tickets.update');
        Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
        Route::get('attachments/{message}', [AdminTicketController::class, 'attachment'])->name('tickets.attachment');
        Route::get('settings', [AdminSettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

    Route::prefix('provider')->name('provider.')->middleware('can:access-water-provider')->group(function () {
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
        Route::post('tickets/{ticket}/escalate', [OperationsController::class, 'escalate'])->middleware('can:tickets.respond')->name('tickets.escalate');
        Route::post('tickets/{ticket}/reply', [OperationsController::class, 'replyTicket'])->middleware('can:tickets.respond')->name('tickets.reply');
        Route::get('attachments/{message}', [OperationsController::class, 'attachment'])->middleware('can:tickets.view')->name('tickets.attachment');
    });

    Route::prefix('portal')->name('portal.')->middleware('can:access-consumer')->group(function () {
        Route::post('tickets', [ConsumerTicketController::class, 'store'])->middleware('can:tickets.create')->name('tickets.store');
        Route::get('tickets/{ticket}', [ConsumerTicketController::class, 'show'])->middleware('can:tickets.view')->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [ConsumerTicketController::class, 'reply'])->middleware('can:tickets.respond')->name('tickets.reply');
        Route::get('attachments/{message}', [ConsumerTicketController::class, 'attachment'])->middleware('can:tickets.view')->name('tickets.attachment');
    });
});

require __DIR__.'/settings.php';
