let loadingTimer = null;
let loadingCycle = 0;

function resetPageLoading() {
    loadingCycle++;
    window.clearTimeout(loadingTimer);
    loadingTimer = null;

    for (const shell of document.querySelectorAll('[data-page-shell]')) {
        shell.setAttribute('aria-busy', 'false');
        const skeleton = shell.querySelector('[data-page-loading]');
        const content = shell.querySelector('[data-page-content]');
        skeleton.hidden = true;
        skeleton.setAttribute('aria-hidden', 'true');
        content.hidden = false;
        content.inert = false;
    }
}

function beginPageLoading(event) {
    const cycle = loadingCycle;
    queueMicrotask(() => {
        if (event.defaultPrevented || cycle !== loadingCycle) return;
        window.clearTimeout(loadingTimer);
        loadingTimer = window.setTimeout(() => {
            const shell = document.querySelector('[data-page-shell]');
            if (!shell) return;
            shell.setAttribute('aria-busy', 'true');
            const skeleton = shell.querySelector('[data-page-loading]');
            const content = shell.querySelector('[data-page-content]');
            skeleton.hidden = false;
            skeleton.setAttribute('aria-hidden', 'false');
            content.hidden = true;
            content.inert = true;
        }, 150);
    });
}

function isPageDestination(href) {
    const destination = new URL(href, window.location.href);
    if (destination.origin !== window.location.origin) return false;
    if (/\/(?:documents|attachments)\/|\/proof\/?$|\/reports\/csv\/?$/.test(destination.pathname)) return false;
    return destination.pathname !== window.location.pathname || destination.search !== window.location.search;
}

document.addEventListener('livewire:navigate', beginPageLoading);
document.addEventListener('livewire:navigating', resetPageLoading);
document.addEventListener('livewire:navigated', resetPageLoading);
window.addEventListener('pageshow', resetPageLoading);
window.addEventListener('error', resetPageLoading);
window.addEventListener('unhandledrejection', resetPageLoading);

document.addEventListener('click', (event) => {
    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target?.closest?.('a[href]');
    if (!link || link.hasAttribute('download') || link.hasAttribute('data-document-preview')) return;
    if ([...link.attributes].some(attribute => attribute.name.startsWith('wire:navigate'))) return;
    if (link.target && link.target !== '_self') return;
    if (isPageDestination(link.href)) beginPageLoading(event);
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.tagName !== 'FORM' || form.method === 'dialog') return;
    if ([...form.attributes].some(attribute => attribute.name.startsWith('wire:submit'))) return;
    if (form.target && form.target !== '_self') return;
    const action = new URL(form.action, window.location.href);
    if (action.origin === window.location.origin) beginPageLoading(event);
});
