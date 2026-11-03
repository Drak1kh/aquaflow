<x-layouts::app title="My Account">
<div class="client-page w-full min-w-0 space-y-6">
<x-page-header title="My Account" description="Your registered water account information. Contact your provider to correct official account details." />
<dl class="grid gap-6 rounded-xl border border-zinc-200 p-6 sm:grid-cols-2 dark:border-zinc-700">
@foreach (['Account number' => $consumer->account_number, 'Registered name' => $consumer->name, 'Contact number' => $consumer->phone ?: 'Not provided', 'Email address' => auth()->user()->email, 'Service address' => $consumer->address, 'Water provider' => $provider->name, 'Portal account status' => $consumer->is_active ? 'Active' : 'Inactive', 'Registered since' => $consumer->created_at->format('M d, Y')] as $label => $value)
<div><dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-2 font-medium">{{ $value }}</dd></div>
@endforeach
</dl>
<flux:button :href="route('portal.settings')">Update contact information</flux:button>
</div>
</x-layouts::app>
