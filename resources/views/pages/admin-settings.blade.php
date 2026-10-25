<x-layouts::app :title="__('System settings')">
    <main class="flex w-full min-w-0 flex-col gap-6 py-8 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">System settings</flux:heading><flux:text>Platform identity, provider defaults, and notification preferences</flux:text></header>
        @include('partials.aquaflow-alerts')
        <flux:card class="space-y-4">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">@csrf @method('PUT')
                <div class="grid gap-4 lg:grid-cols-2">
                    <flux:input name="platform_name" label="Platform name" value="{{ $platformName }}" required />
                    <flux:input name="support_email" label="Support email" type="email" value="{{ $supportEmail }}" />
                </div>
                <flux:heading size="lg">Defaults for new providers</flux:heading>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <flux:input name="default_grace_days" label="Default grace days" type="number" min="0" value="{{ $defaultGraceDays }}" required />
                    <flux:input name="default_disconnection_days" label="Default disconnection days" type="number" min="1" value="{{ $defaultDisconnectionDays }}" required />
                    <flux:input name="default_late_fee" label="Default late fee" type="number" step="0.01" min="0" value="{{ $defaultLateFee }}" required />
                    <flux:input name="default_reconnection_fee" label="Default reconnection fee" type="number" step="0.01" min="0" value="{{ $defaultReconnectionFee }}" required />
                </div>
                <flux:heading size="lg">Consumer notification defaults</flux:heading>
                <p class="text-sm text-slate-600 dark:text-slate-300">These apply until a consumer saves their own notification preferences. Service notices remain enabled.</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:select name="notification_billing_default" label="Billing notifications">
                        <flux:select.option value="1" :selected="$notificationBillingDefault === '1'">On by default</flux:select.option>
                        <flux:select.option value="0" :selected="$notificationBillingDefault === '0'">Off by default</flux:select.option>
                    </flux:select>
                    <flux:select name="notification_payment_default" label="Payment notifications">
                        <flux:select.option value="1" :selected="$notificationPaymentDefault === '1'">On by default</flux:select.option>
                        <flux:select.option value="0" :selected="$notificationPaymentDefault === '0'">Off by default</flux:select.option>
                    </flux:select>
                    <flux:select name="notification_support_default" label="Support notifications">
                        <flux:select.option value="1" :selected="$notificationSupportDefault === '1'">On by default</flux:select.option>
                        <flux:select.option value="0" :selected="$notificationSupportDefault === '0'">Off by default</flux:select.option>
                    </flux:select>
                </div>
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </form>
        </flux:card>
    </main>
</x-layouts::app>
