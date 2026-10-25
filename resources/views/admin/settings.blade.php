<x-layouts::app :title="__('System settings')">
    <div class="w-full min-w-0 space-y-7">
        <x-page-header :title="__('System settings')" :description="__('Platform defaults apply when a provider has no override.')">
            <x-slot:actions>
                <flux:modal.trigger name="edit-system-settings">
                    <flux:button variant="primary" icon="pencil-square">{{ __('Edit settings') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Platform') }}</flux:heading>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="text-zinc-500">{{ __('Platform name') }}</dt><dd class="mt-1 font-medium">{{ $values['platform_name'] }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Date format') }}</dt><dd class="mt-1 font-medium">{{ now()->format($values['date_format']) }}</dd></div>
                </dl>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Billing defaults') }}</flux:heading>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="text-zinc-500">{{ __('Days until due') }}</dt><dd class="mt-1 font-medium">{{ $values['due_days'] }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Grace days') }}</dt><dd class="mt-1 font-medium">{{ $values['grace_days'] }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Fixed late fee') }}</dt><dd class="mt-1 font-medium tabular-nums">₱{{ number_format((float) $values['late_fee'], 2) }}</dd></div>
                </dl>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
                <flux:text class="mt-3">{{ __('Email active Super Admins when a provider escalates a ticket.') }}</flux:text>
                <div class="mt-5"><flux:badge :color="$values['email_notifications'] === '1' ? 'green' : 'zinc'">{{ $values['email_notifications'] === '1' ? __('Enabled') : __('Disabled') }}</flux:badge></div>
            </section>
        </div>

        <x-form-modal name="edit-system-settings" :title="__('Edit system settings')" :description="__('Changes to billing defaults apply to future bills.')" :action="route('admin.settings.update')" method="PUT" :submit-label="__('Save settings')">
            @php($editingSettings = old('_form') === 'edit-system-settings')
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input name="platform_name" :value="$editingSettings ? old('platform_name') : $values['platform_name']" :label="__('Platform name')" required />
                <flux:select name="date_format" :label="__('Date format')">
                    @foreach (['M j, Y' => 'Oct 25, 2026', 'Y-m-d' => '2026-10-25', 'd/m/Y' => '25/10/2026'] as $format => $sample)
                        <option value="{{ $format }}" @selected(($editingSettings ? old('date_format') : $values['date_format']) === $format)>{{ $sample }}</option>
                    @endforeach
                </flux:select>
                <flux:input name="due_days" type="number" min="0" max="90" :value="$editingSettings ? old('due_days') : $values['due_days']" :label="__('Days until due')" required />
                <flux:input name="grace_days" type="number" min="0" max="90" :value="$editingSettings ? old('grace_days') : $values['grace_days']" :label="__('Grace days')" required />
                <flux:input name="late_fee" type="number" min="0" step="0.01" :value="$editingSettings ? old('late_fee') : $values['late_fee']" :label="__('Fixed late fee (PHP)')" required />
                <flux:select name="email_notifications" :label="__('Escalated ticket email alerts')">
                    <option value="0" @selected(($editingSettings ? old('email_notifications') : $values['email_notifications']) === '0')>{{ __('Disabled') }}</option>
                    <option value="1" @selected(($editingSettings ? old('email_notifications') : $values['email_notifications']) === '1')>{{ __('Enabled') }}</option>
                </flux:select>
            </div>
        </x-form-modal>
    </div>
</x-layouts::app>
