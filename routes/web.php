<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ConsumerPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('providers/switch', [AdminController::class, 'switchProvider'])->name('providers.switch');

    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');
    Route::livewire('admin/providers', 'pages::admin-providers')->name('admin.providers.index');
    Route::livewire('admin/users', 'pages::admin-users')->name('admin.users.index');
    Route::post('admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::post('admin/users/{user}/memberships', [AdminController::class, 'saveMembership'])->name('admin.users.memberships.store');
    Route::livewire('admin/roles', 'pages::admin-roles')->name('admin.roles.index');
    Route::get('admin/overview', [AdminController::class, 'overview'])->name('admin.overview');
    Route::get('admin/overview/export', [AdminController::class, 'exportOverview'])->name('admin.overview.export');
    Route::get('admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::put('admin/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::get('admin/support', [SupportController::class, 'adminIndex'])->name('admin.support');
    Route::post('admin/support/{ticket}/reply', [SupportController::class, 'adminReply'])->name('admin.support.reply');
    Route::post('admin/support/{ticket}/resolve', [SupportController::class, 'resolve'])->name('admin.support.resolve');
    Route::post('admin/providers', [AdminController::class, 'storeProvider'])->name('admin.providers.store');
    Route::put('admin/providers/{provider}', [AdminController::class, 'updateProvider'])->name('admin.providers.update');
    Route::put('admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::post('admin/staff', [AdminController::class, 'inviteStaff'])->name('admin.staff.invite');
    Route::post('admin/roles', [AdminController::class, 'storeRole'])->name('admin.roles.store');
    Route::put('admin/roles/{role}', [AdminController::class, 'updateRole'])->name('admin.roles.update');

    Route::get('provider', [ProviderController::class, 'index'])->name('provider.index');
    Route::put('provider/profile', [ProviderController::class, 'updateProfile'])->name('provider.profile.update');
    Route::post('provider/consumers', [ProviderController::class, 'storeConsumer'])->name('provider.consumers.store');
    Route::put('provider/consumers/{consumer}', [ProviderController::class, 'updateConsumer'])->name('provider.consumers.update');
    Route::post('provider/connections', [ProviderController::class, 'storeConnection'])->name('provider.connections.store');
    Route::post('provider/connections/{connection}/meter', [ProviderController::class, 'replaceMeter'])->name('provider.meters.replace');
    Route::post('provider/connections/{connection}/readings', [ProviderController::class, 'storeReading'])->name('provider.readings.store');
    Route::post('provider/consumers/{consumer}/invite', [ProviderController::class, 'inviteConsumer'])->name('provider.consumers.invite');
    Route::get('provider/report', [ProviderController::class, 'report'])->name('provider.report');
    Route::get('provider/report/export', [ProviderController::class, 'exportReport'])->name('provider.report.export');

    Route::get('provider/billing', [BillingController::class, 'index'])->name('provider.billing');
    Route::get('provider/billing/preview', [BillingController::class, 'preview'])->name('provider.bills.preview');
    Route::post('provider/rates', [BillingController::class, 'storeRate'])->name('provider.rates.store');
    Route::post('provider/periods', [BillingController::class, 'storePeriod'])->name('provider.periods.store');
    Route::post('provider/bills', [BillingController::class, 'generate'])->name('provider.bills.generate');
    Route::post('provider/bills/batch', [BillingController::class, 'batch'])->name('provider.bills.batch');
    Route::post('provider/bills/{bill}/adjust', [BillingController::class, 'adjust'])->name('provider.bills.adjust');
    Route::post('provider/bills/{bill}/void', [BillingController::class, 'void'])->name('provider.bills.void');
    Route::get('provider/bills/{bill}', [BillingController::class, 'show'])->name('provider.bills.show');

    Route::get('provider/payments', [PaymentController::class, 'index'])->name('provider.payments');
    Route::post('provider/payments', [PaymentController::class, 'store'])->name('provider.payments.store');
    Route::post('provider/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('provider.payments.verify');
    Route::post('provider/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('provider.payments.reject');
    Route::post('provider/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('provider.payments.reverse');
    Route::get('provider/payments/{payment}/proof', [PaymentController::class, 'proof'])->name('provider.payments.proof');
    Route::get('provider/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('provider.payments.receipt');

    Route::get('provider/service', [ServiceController::class, 'index'])->name('provider.service');
    Route::get('provider/support', [SupportController::class, 'providerIndex'])->name('provider.support');
    Route::post('provider/support/{ticket}/reply', [SupportController::class, 'reply'])->name('provider.support.reply');
    Route::post('provider/support/{ticket}/escalate', [SupportController::class, 'escalate'])->name('provider.support.escalate');
    Route::post('provider/support/{ticket}/resolve', [SupportController::class, 'resolve'])->name('provider.support.resolve');
    Route::put('provider/service/rules', [ServiceController::class, 'updateRules'])->name('provider.service.rules');
    Route::post('provider/connections/{connection}/service', [ServiceController::class, 'act'])->name('provider.service.act');
    Route::get('provider/service/notices/{action}', [ServiceController::class, 'notice'])->name('provider.service.notice');

    Route::get('my-account', [ConsumerPortalController::class, 'index'])->name('consumer.index');
    Route::put('my-account/contact', [ConsumerPortalController::class, 'updateContact'])->name('consumer.contact.update');
    Route::put('my-account/notifications/preferences', [ConsumerPortalController::class, 'updatePreferences'])->name('consumer.notifications.preferences');
    Route::get('my-account/support', [SupportController::class, 'consumerIndex'])->name('consumer.support');
    Route::post('my-account/support', [SupportController::class, 'store'])->name('consumer.support.store');
    Route::get('my-account/bills/{bill}', [ConsumerPortalController::class, 'showBill'])->name('consumer.bills.show');
    Route::post('my-account/payments', [ConsumerPortalController::class, 'submitPayment'])->name('consumer.payments.submit');
    Route::get('my-account/payments/{payment}/receipt', [ConsumerPortalController::class, 'receipt'])->name('consumer.payments.receipt');
    Route::get('my-account/notices/{action}', [ConsumerPortalController::class, 'notice'])->name('consumer.service.notice');
    Route::post('my-account/notifications/{notification}/read', [ConsumerPortalController::class, 'readNotification'])->name('consumer.notifications.read');
    Route::get('support/attachments/{attachment}', [SupportController::class, 'attachment'])->name('support.attachments.show');
});

require __DIR__.'/settings.php';
