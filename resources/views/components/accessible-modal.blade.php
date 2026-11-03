@props(['name', 'show' => false])

<div class="contents" x-data x-init="$nextTick(() => {
    const dialog = $el.querySelector('dialog');
    const focusFirst = () => dialog.querySelector('input:not([type=hidden]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus();
    new MutationObserver(() => { if (dialog.open) $nextTick(focusFirst); }).observe(dialog, { attributes: true, attributeFilter: ['open'] });
    if (dialog.open) focusFirst();
})" x-on:keydown.tab="
    const items = [...$el.querySelectorAll('button:not([disabled]), a[href], input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]')].filter(item => item.tabIndex >= 0 && item.getClientRects().length);
    const first = items[0];
    const last = items[items.length - 1];
    if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last?.focus(); }
    if (!$event.shiftKey && document.activeElement === last) { $event.preventDefault(); first?.focus(); }
">
    <flux:modal :name="$name" :show="$show" :class="$attributes->get('class')">
        {{ $slot }}
    </flux:modal>
</div>
