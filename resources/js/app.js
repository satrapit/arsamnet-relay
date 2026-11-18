// Same-origin Vite entry: progressive enhancements without inline handlers.
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const close = document.querySelector('[data-sidebar-close]');
    const desktop = window.matchMedia('(min-width: 1024px)');

    const closedClass = document.documentElement.dir === 'rtl' ? 'translate-x-full' : '-translate-x-full';

    function setOpen(open) {
        const mobileOpen = open && !desktop.matches;
        sidebar?.classList.toggle(closedClass, !mobileOpen);
        backdrop?.classList.toggle('hidden', !mobileOpen);
        toggle?.setAttribute('aria-expanded', String(mobileOpen));
        sidebar?.setAttribute('aria-hidden', String(!desktop.matches && !mobileOpen));
        if (sidebar) sidebar.inert = !desktop.matches && !mobileOpen;
        document.body.classList.toggle('overflow-hidden', mobileOpen);
        if (mobileOpen) close?.focus();
    }

    setOpen(false);
    toggle?.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    close?.addEventListener('click', () => { setOpen(false); toggle?.focus(); });
    backdrop?.addEventListener('click', () => { setOpen(false); toggle?.focus(); });
    sidebar?.querySelectorAll('nav a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
    desktop.addEventListener('change', () => setOpen(false));

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });

    document.querySelectorAll('[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitted === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.submitted = 'true';
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.setAttribute('aria-busy', 'true');
                button.dataset.loading = 'true';
                // Keep the submitter enabled until the browser completes submission.
                queueMicrotask(() => { button.disabled = true; });
            }
        });
    });
});
