<x-layouts::app :title="$user->name">
    <div class="w-full min-w-0 space-y-7">
        <x-page-header :title="$user->name" :description="$user->email" :back-href="route('admin.users.index')" :back-label="__('Users')" />
        <dl class="grid gap-4 rounded-xl border border-zinc-200 bg-white p-6 sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-900">
            <div><dt class="text-sm text-zinc-500">{{ __('Role') }}</dt><dd class="mt-1 font-medium">{{ $user->role->label() }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('Status') }}</dt><dd class="mt-1 font-medium">{{ $user->is_active ? __('Active') : __('Inactive') }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('Provider') }}</dt><dd class="mt-1">{{ $user->waterProvider?->name ?? $user->consumer?->waterProvider?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('Consumer account') }}</dt><dd class="mt-1">{{ $user->consumer?->account_number ?? '—' }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('Email verification') }}</dt><dd class="mt-1">{{ $user->email_verified_at ? __('Verified') : __('Pending') }}</dd></div>
            <div><dt class="text-sm text-zinc-500">{{ __('Created') }}</dt><dd class="mt-1">{{ $user->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format')) }}</dd></div>
        </dl>
        <section><flux:heading size="lg">{{ __('Account activity') }}</flux:heading>
            <div class="mt-3 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                @forelse ($activity as $event)
                    <div class="flex justify-between gap-3 px-5 py-3 text-sm"><span>{{ str_replace('.', ' · ', $event->action) }}</span><time class="text-zinc-500">{{ $event->created_at->format(app(\App\Actions\PlatformSettings::class)->get('date_format').' g:i A') }}</time></div>
                @empty
                    <div class="px-5 py-8 text-sm">{{ __('No account changes have been logged.') }}</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::app>
