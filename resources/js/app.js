const landingPage = document.querySelector('body.landing');

if (landingPage && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    for (const sectionContent of landingPage.querySelectorAll('main > section:not(.landing-hero) > .landing-container')) {
        sectionContent.classList.add('landing-reveal');
        revealObserver.observe(sectionContent);
    }
}
