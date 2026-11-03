<div x-data="{
    url: null,
    title: @js(__('Document preview')),
    loading: false,
    ready: false,
    open(event) {
        const link = event.target.closest('a[data-document-preview]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        event.preventDefault();
        url.searchParams.set('preview', '1');
        if (this.url === url.href && this.ready) {
            $flux.modal('document-preview').show();
            return;
        }
        this.title = @js(__('Document preview'));
        this.loading = true;
        this.ready = false;
        this.url = url.href;
        $flux.modal('document-preview').show();
    },
    loaded() {
        if (!this.url) return;
        this.loading = false;
        try {
            const document = this.$refs.documentFrame.contentDocument;
            this.ready = Boolean(document?.querySelector('[data-print-document]'));
            if (this.ready) this.title = document.title;
        } catch { this.ready = false; }
    },
    print() {
        if (!this.ready) return;
        this.$refs.documentFrame.contentWindow.focus();
        this.$refs.documentFrame.contentWindow.print();
    },
    reset() { this.url = null; this.loading = false; this.ready = false; },
}" x-on:click.document="open($event)">
    <flux:modal name="document-preview" variant="bare" :closable="false" class="flex h-[90dvh] w-[calc(100vw-2rem)] min-w-0 max-w-6xl flex-col overflow-hidden rounded-xl bg-white p-0 shadow-lg dark:bg-zinc-900" x-on:close="reset()">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white px-5 py-4 text-zinc-900 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <h2 class="min-w-0 flex-1 basis-full break-words text-lg font-semibold sm:basis-0" x-text="title">{{ __('Document preview') }}</h2>
            <div class="flex items-center gap-2">
                <flux:button icon="printer" x-on:click="print()" x-bind:disabled="!ready">{{ __('Print / save PDF') }}</flux:button>
                <flux:modal.close><flux:button icon="x-mark" :aria-label="__('Close document preview')" /></flux:modal.close>
            </div>
        </header>
        <div class="relative min-h-0 flex-1 bg-white">
            <div x-show="loading" class="flex h-full items-center justify-center text-sm text-zinc-600" role="status">{{ __('Loading document…') }}</div>
            <div x-cloak x-show="url && !loading && !ready" class="flex h-full flex-col items-center justify-center gap-4 p-6 text-center text-zinc-700">
                <p>{{ __('This document could not be loaded. Please reopen it or check your access.') }}</p>
                <a x-bind:href="url" class="rounded-sm font-medium text-zinc-900 underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4">{{ __('Open document') }}</a>
            </div>
            <iframe x-ref="documentFrame" x-bind:src="url || 'about:blank'" x-bind:title="title" x-on:load="loaded()" x-show="url && !loading && ready" class="h-full w-full border-0" title="{{ __('Document preview') }}"></iframe>
        </div>
    </flux:modal>
</div>
