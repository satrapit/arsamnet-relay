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

    // Settings use same-origin JSON updates; all authorization and audit
    // remain server-side. Minimal Tailwind toast, no external CDN/dependency.
    const notifications = document.querySelector('[data-settings-toasts]');
    if (notifications) {
        function toast(message, successful = true) {
            const item = document.createElement('div');
            item.className = 'pointer-events-auto flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3.5 shadow-xl shadow-slate-900/10';
            item.setAttribute('role', successful ? 'status' : 'alert');

            const indicator = document.createElement('span');
            indicator.className = 'flex size-9 shrink-0 items-center justify-center rounded-xl ' +
                (successful ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
            const icon = document.createElement('i');
            icon.className = 'fa-solid ' + (successful ? 'fa-circle-check' : 'fa-circle-exclamation');
            icon.setAttribute('aria-hidden', 'true');
            indicator.append(icon);

            const label = document.createElement('p');
            label.className = 'min-w-0 flex-1 text-sm font-medium text-slate-800';
            label.textContent = message;

            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'rounded-lg p-1 text-slate-400 hover:text-slate-700';
            dismiss.setAttribute('aria-label', 'Dismiss notification');
            dismiss.textContent = '×';
            dismiss.addEventListener('click', () => item.remove());

            item.append(indicator, label, dismiss);
            notifications.prepend(item);
            while (notifications.children.length > 3) notifications.lastElementChild?.remove();
            window.setTimeout(() => item.remove(), 5000);
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        async function save(form) {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf || '',
                },
            });
            const type = response.headers.get('content-type') || '';
            if (!type.includes('application/json')) {
                throw new Error('Your session may have expired. Sign in and try again.');
            }
            const payload = await response.json();
            if (!response.ok) {
                const detail = Object.values(payload.errors || {}).flat()[0];
                throw new Error(detail || payload.message || 'Unable to save the setting.');
            }
            return payload;
        }

        function confirmLive() {
            const modal = document.querySelector('[data-settings-confirm]');
            if (!modal?.showModal) return Promise.resolve(window.confirm('Enable potentially billable live SMS sending?'));

            return new Promise((resolve) => {
                const yes = modal.querySelector('[data-settings-confirm-accept]');
                const no = modal.querySelector('[data-settings-cancel]');
                const finish = (approved) => {
                    yes.removeEventListener('click', approvedHandler);
                    no.removeEventListener('click', cancelHandler);
                    modal.removeEventListener('cancel', cancelEvent);
                    modal.close();
                    resolve(approved);
                };
                const approvedHandler = () => finish(true);
                const cancelHandler = () => finish(false);
                const cancelEvent = (event) => {
                    event.preventDefault();
                    finish(false);
                };
                yes.addEventListener('click', approvedHandler);
                no.addEventListener('click', cancelHandler);
                modal.addEventListener('cancel', cancelEvent);
                modal.showModal();
                no.focus();
            });
        }

        document.querySelectorAll('[data-settings-toggle]').forEach((form) => {
            const input = form.querySelector('input[type="checkbox"]');
            if (!input || input.disabled) return;
            let saved = input.checked;
            form.addEventListener('submit', (event) => event.preventDefault());
            input.addEventListener('change', async () => {
                const intended = input.checked;
                if (intended && form.hasAttribute('data-confirm-live') && !(await confirmLive())) {
                    input.checked = saved;
                    return;
                }

                input.disabled = true;
                input.setAttribute('aria-busy', 'true');
                try {
                    // Disabled inputs are omitted from FormData, so capture
                    // before changing the disabled state in save().
                    input.disabled = false;
                    const payload = await save(form);
                    input.disabled = true;
                    saved = payload.enabled === true;
                    input.checked = saved;
                    input.defaultChecked = saved;
                    input.setAttribute('aria-checked', String(saved));
                    form.querySelector('[data-switch-state]').textContent = saved ? 'On' : 'Off';
                    toast(form.dataset.settingLabel + (saved ? ' enabled.' : ' disabled.'));
                } catch (error) {
                    input.checked = saved;
                    toast(error.message || 'Could not save the setting.', false);
                } finally {
                    input.disabled = false;
                    input.removeAttribute('aria-busy');
                }
            });
        });

        document.querySelectorAll('[data-settings-limit]').forEach((form) => {
            const button = form.querySelector('button[type="submit"]');
            const field = form.querySelector('[name="hourly_limit"]');
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!field?.reportValidity() || !button || button.disabled) return;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                try {
                    const result = await save(form);
                    field.value = String(result.hourly_limit);
                    toast('Kavenegar hourly limit saved.');
                } catch (error) {
                    toast(error.message || 'Could not save the hourly limit.', false);
                } finally {
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }
            });
        });
    }

});
