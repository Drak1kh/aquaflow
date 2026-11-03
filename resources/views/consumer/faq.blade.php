<x-layouts::app title="Frequently asked questions">
    <div class="client-page w-full min-w-0 space-y-6">
        <x-page-header title="Frequently asked questions" description="Answers about your water account, bills, payments, and service.">
            <x-slot:actions>
                <flux:button :href="route('portal.section', 'support')">Contact support</flux:button>
            </x-slot:actions>
        </x-page-header>
        <section class="space-y-3">
            @foreach ([
                'How is my consumption calculated?' => 'Consumption is the current meter reading minus the previous reading. Bills preserve the readings and rate used when they were generated.',
                'How are water charges calculated?' => 'The bill includes the configured base charge plus consumption multiplied by the rate per cubic meter. Approved adjustments or applicable penalties are shown on the bill.',
                'Why is my payment still pending?' => 'Your provider must verify submitted payment details and proof before applying the payment. If rejected, review the reason under Payments and contact your provider.',
                'Can I make a partial payment?' => 'Yes. Submit an amount up to the remaining bill balance. A verified partial payment reduces the balance; the bill is paid only when the remaining balance reaches zero.',
                'Can I change my meter or registered address?' => 'Official account, meter, and connection records are maintained by your provider. Submit a support concern to request a correction.',
                'Does payment automatically reconnect service?' => 'No. Your provider reviews the account and records a reconnection request and completed restoration separately.',
            ] as $question => $answer)
                <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"><summary class="cursor-pointer font-medium">{{ $question }}</summary><p class="mt-3 text-sm leading-relaxed text-zinc-600 dark:text-zinc-300">{{ $answer }}</p></details>
            @endforeach
        </section>
    </div>
</x-layouts::app>
