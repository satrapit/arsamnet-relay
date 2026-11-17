// Executed as a same-origin, versioned Vite module. No runtime CDN dependency.
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('hidden');
    });
    document.querySelectorAll('[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type=submit]');
            if (button) {
                button.disabled = true;
                button.dataset.loading = 'true';
            }
        });
    });
});
