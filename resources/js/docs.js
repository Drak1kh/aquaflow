const documentationRoles = ['consumer', 'provider', 'admin'];

export function documentationLocation(href, topics) {
    const url = new URL(href);
    const requestedRole = url.searchParams.get('role');
    let role = documentationRoles.includes(requestedRole) ? requestedRole : 'consumer';
    const topic = topics.find(item => item.id === url.hash.slice(1));
    if (topic && topic.role !== 'shared') role = topic.role;
    return { role, topic: topic?.id || '' };
}

export function filterDocumentationTopics(topics, role, query) {
    const words = query.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    return topics.filter(topic => (topic.role === role || topic.role === 'shared')
        && words.every(word => topic.text.toLocaleLowerCase().includes(word)));
}

let activeDocumentationRoot = null;
let cleanupDocumentation = () => {};

function initializeDocumentation() {
    const root = document.querySelector('[data-docs]');
    if (root === activeDocumentationRoot) return;
    cleanupDocumentation();
    activeDocumentationRoot = root;
    if (!root) return;

    const controller = new AbortController();
    const options = { signal: controller.signal };
    cleanupDocumentation = () => controller.abort();
    const topics = [...root.querySelectorAll('[data-doc-topic]')].map(element => ({
        element,
        id: element.id,
        role: element.dataset.docRole,
        title: element.querySelector('h3').textContent,
        text: element.textContent,
    }));
    const search = root.querySelector('[data-doc-search]');
    const clear = root.querySelector('[data-doc-clear]');
    const status = root.querySelector('[data-doc-search-status]');
    const contents = root.querySelector('[data-doc-toc]');
    const selector = root.querySelector('[data-doc-topic-select]');
    let state = documentationLocation(window.location.href, topics);
    let query = '';

    function topicUrl(topic) {
        const url = new URL(window.location.href);
        url.searchParams.set('role', topic.role === 'shared' ? state.role : topic.role);
        url.hash = topic.id;
        return url.href;
    }

    function render() {
        const visible = filterDocumentationTopics(topics, state.role, query);
        const visibleIds = new Set(visible.map(topic => topic.id));
        for (const topic of topics) topic.element.hidden = !visibleIds.has(topic.id);
        for (const guide of root.querySelectorAll('[data-doc-guide]')) {
            guide.hidden = !visible.some(topic => topic.role === guide.dataset.docGuide);
        }
        for (const link of root.querySelectorAll('[data-doc-role-link]')) {
            if (link.dataset.docRoleLink === state.role) link.setAttribute('aria-current', 'true');
            else link.removeAttribute('aria-current');
        }

        const heading = document.createElement('h2');
        heading.textContent = 'In this guide';
        const list = document.createElement('ul');
        selector.replaceChildren(new Option(visible.length ? 'Choose a topic' : 'No matching topics', ''));
        for (const topic of visible) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = topicUrl(topic);
            link.dataset.docTopicLink = topic.id;
            link.textContent = topic.title;
            if (topic.id === state.topic) link.setAttribute('aria-current', 'location');
            item.append(link);
            list.append(item);
            selector.append(new Option(topic.title, topic.id, false, topic.id === state.topic));
        }
        contents.replaceChildren(heading, list);
        if (!visible.length) {
            const note = document.createElement('p');
            note.className = 'docs-note';
            note.textContent = 'No matching topics.';
            contents.append(note);
        }
        status.hidden = query.trim() === '';
        status.textContent = visible.length
            ? visible.length + (visible.length === 1 ? ' topic found.' : ' topics found.')
            : 'No topics found. Try “payment”, “meter reading”, or “password”, or clear your search.';
        clear.hidden = query === '';
    }

    function scrollToTopic() {
        const topic = topics.find(item => item.id === state.topic);
        if (!topic || topic.element.hidden) return;
        topic.element.scrollIntoView({ block: 'start' });
        topic.element.querySelector('h3').focus({ preventScroll: true });
    }

    function navigate(role, id) {
        state = { role, topic: id };
        query = '';
        search.value = '';
        const url = new URL(window.location.href);
        url.searchParams.set('role', role);
        url.hash = id;
        if (url.href !== window.location.href) window.history.pushState(null, '', url);
        render();
        scrollToTopic();
    }

    root.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.defaultPrevented) return;
        const link = event.target.closest?.('a[data-doc-role-link], a[data-doc-topic-link]');
        if (!link) return;
        if (link.hasAttribute('data-doc-role-link')) {
            event.preventDefault();
            const role = link.dataset.docRoleLink;
            navigate(role, role + '-start');
        } else {
            const topic = topics.find(item => item.id === link.dataset.docTopicLink);
            if (!topic) return;
            event.preventDefault();
            navigate(topic.role === 'shared' ? state.role : topic.role, topic.id);
        }
    }, options);
    search.addEventListener('input', () => { query = search.value; render(); }, options);
    clear.addEventListener('click', () => {
        query = '';
        search.value = '';
        render();
        search.focus();
    }, options);
    selector.addEventListener('change', () => {
        const topic = topics.find(item => item.id === selector.value);
        if (topic) navigate(topic.role === 'shared' ? state.role : topic.role, topic.id);
    }, options);
    const restoreLocation = () => {
        state = documentationLocation(window.location.href, topics);
        query = '';
        search.value = '';
        render();
        scrollToTopic();
    };
    window.addEventListener('popstate', restoreLocation, options);
    window.addEventListener('hashchange', restoreLocation, options);
    render();
    scrollToTopic();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDocumentation, { once: true });
    } else {
        initializeDocumentation();
    }
    document.addEventListener('livewire:navigated', initializeDocumentation);
}
