<x-layouts::app :title="__('System settings')">
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6 text-[#062E68] dark:text-white">
        <header><flux:heading size="xl">System settings</flux:heading><flux:text>Platform identity, support contact, and defaults for new providers</flux:text></header>
        @include('partials.aquaflow-alerts')
        <flux:card class="space-y-4">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">@csrf @method('PUT')
                <flux:input name="platform_name" label="Platform name" value="{{ $platformName }}" required />
                <flux:input name="support_email" label="Support email" type="email" value="{{ $supportEmail }}" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input name="default_grace_days" label="Default grace days" type="number" min="0" value="{{ $defaultGraceDays }}" required />
                    <flux:input name="default_disconnection_days" label="Default disconnection days" type="number" min="1" value="{{ $defaultDisconnectionDays }}" required />
                    <flux:input name="default_late_fee" label="Default late fee" type="number" step="0.01" min="0" value="{{ $defaultLateFee }}" required />
                </div>
                <flux:button type="submit" variant="primary">Save settings</flux:button>
            </form>
        </flux:card>
    </main>
</x-layouts::app>
