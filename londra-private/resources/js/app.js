(() => {
    'use strict';
    let ui = {};
    try { ui = JSON.parse(document.body.dataset.ui || '{}'); } catch { return; }
    document.documentElement.classList.add('js');
    const themeButton = document.querySelector('[data-theme-toggle]');
    const syncTheme = () => {
        const isLight = document.documentElement.dataset.theme === 'light';
        themeButton?.setAttribute('aria-pressed', String(isLight));
        themeButton?.setAttribute('aria-label', ui.light);
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', isLight ? '#F7F7F5' : '#0B0B0B');
    };
    themeButton?.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('londra-theme', next); } catch {}
        syncTheme();
    });
    syncTheme();
    const actionbar = document.querySelector('.mobile-actionbar');
    if (actionbar && 'ResizeObserver' in window) {
        let frame = 0;
        new ResizeObserver(() => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                document.documentElement.style.setProperty('--actionbar-height', `${Math.ceil(actionbar.getBoundingClientRect().height)}px`);
            });
        }).observe(actionbar);
    }
    document.querySelectorAll('.language-switch').forEach(details => {
        details.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                details.open = false;
                details.querySelector('summary')?.focus();
            }
        });
        document.addEventListener('click', event => {
            if (!details.contains(event.target)) details.open = false;
        });
    });
    const configureDialog = (id, openSelector, closeSelector, onOpen) => {
        const dialog = document.getElementById(id);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        document.documentElement.classList.add('has-dialogs');
        let opener = null;
        document.querySelectorAll(openSelector).forEach(trigger => {
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-controls', id);
            trigger.addEventListener('click', event => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                if (trigger.matches('a[href]') && trigger.target && trigger.target !== '_self') return;
                if (onOpen?.(dialog, trigger) === false) return;
                event.preventDefault();
                opener = trigger;
                document.querySelectorAll('.language-switch[open]').forEach(element => { element.open = false; });
                document.querySelectorAll('dialog[open]').forEach(other => { if (other !== dialog) other.close(); });
                if (!dialog.open) dialog.showModal();
                document.body.classList.add('is-locked');
                dialog.querySelector('h2[tabindex="-1"]')?.focus({preventScroll: true});
            });
        });
        dialog.querySelector(closeSelector)?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
        });
        dialog.addEventListener('close', () => {
            if (!document.querySelector('dialog[open]')) {
                document.body.classList.remove('is-locked');
                if (opener?.isConnected) opener.focus({preventScroll: true});
            }
        });
    };
    configureDialog('navigation-dialog', '[data-open-navigation]', '[data-close-navigation]');
    configureDialog('enquiry-dialog', '[data-open-enquiry]', '[data-close-dialog]', (dialog, trigger) => {
        const service = dialog.querySelector('[name="service_key"]');
        const selected = trigger.dataset.service || '';
        if (service) service.value = selected;
        const context = dialog.querySelector('[data-enquiry-context]');
        if (context) {
            const name = trigger.dataset.serviceName || '';
            context.hidden = !selected || !name;
            context.querySelector('strong').textContent = name;
        }
    });
    document.querySelectorAll('[data-deck]').forEach(deck => {
        const tablist = deck.querySelector('[data-tablist]');
        const tabs = Array.from(deck.querySelectorAll('[data-tab]'));
        const panels = Array.from(deck.querySelectorAll('[data-panel]'));
        if (!tablist || !tabs.length || tabs.length !== panels.length) return;
        tablist.setAttribute('role', 'tablist');
        tablist.setAttribute('aria-orientation', 'horizontal');
        tabs.forEach(tab => tab.setAttribute('role', 'tab'));
        panels.forEach(panel => { panel.setAttribute('role', 'tabpanel'); panel.setAttribute('tabindex', '0'); });
        const activate = (index, focus = false) => {
            const current = (index + tabs.length) % tabs.length;
            tabs.forEach((tab, i) => {
                tab.setAttribute('aria-selected', String(i === current));
                tab.tabIndex = i === current ? 0 : -1;
                panels[i].hidden = i !== current;
            });
            if (focus) tabs[current].focus({preventScroll: true});
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(index));
            tab.addEventListener('keydown', event => {
                const next = event.key === 'ArrowRight' ? index + 1 : event.key === 'ArrowLeft' ? index - 1 : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
                if (next === null) return;
                event.preventDefault();
                activate(next, true);
            });
        });
        deck.classList.add('is-enhanced');
        deck.querySelector('[data-deck-prev]')?.addEventListener('click', () => {
            const current = tabs.findIndex(tab => tab.getAttribute('aria-selected') === 'true');
            activate(current - 1);
        });
        deck.querySelector('[data-deck-next]')?.addEventListener('click', () => {
            const current = tabs.findIndex(tab => tab.getAttribute('aria-selected') === 'true');
            activate(current + 1);
        });
        const requested = Number.parseInt(deck.dataset.initial || '0', 10);
        activate(Number.isFinite(requested) && requested >= 0 && requested < tabs.length ? requested : 0);
    });
    configureDialog('compare-dialog', '[data-open-compare]', '[data-close-compare]');
    configureDialog('gallery-dialog', '[data-open-gallery]', '[data-close-gallery]', (dialog, trigger) => {
        const source = trigger.closest('.fleet-panel')?.querySelector('.fleet-photo img');
        const container = dialog.querySelector('[data-gallery-photo]');
        if (!source || !container) return false;
        const image = source.cloneNode(true);
        image.setAttribute('sizes', '(min-width: 1200px) 1162px, calc(100vw - 68px)');
        image.setAttribute('loading', 'eager');
        image.removeAttribute('fetchpriority');
        container.replaceChildren(image);
        const vehicle = trigger.dataset.vehicleName || '';
        const title = dialog.querySelector('h2');
        if (title) title.textContent = `${ui.gallery_title}${vehicle ? ' · ' + vehicle : ''}`;
    });

    // Individually cleared, multi-angle galleries on each Mercedes vehicle detail page.
    const angleDialog = document.querySelector('[data-fleet-angles-dialog]');
    const angleTriggers = Array.from(document.querySelectorAll('[data-angle-open]'));
    if (angleDialog && angleTriggers.length && typeof angleDialog.showModal === 'function') {
        const large = angleDialog.querySelector('[data-angle-large]');
        const caption = angleDialog.querySelector('[data-angle-caption]');
        const position = angleDialog.querySelector('[data-angle-position]');
        let active = 0;
        let opener = null;
        const show = index => {
            active = (index + angleTriggers.length) % angleTriggers.length;
            const thumb = angleTriggers[active].querySelector('img');
            if (!thumb || !large) return;
            large.src = thumb.currentSrc || thumb.src;
            const responsive = thumb.getAttribute('srcset');
            if (responsive) {
                large.setAttribute('srcset', responsive);
                large.setAttribute('sizes', '(min-width: 1300px) 1120px, calc(100vw - 55px)');
            } else {
                large.removeAttribute('srcset');
                large.removeAttribute('sizes');
            }
            large.alt = thumb.alt || '';
            if (caption) caption.textContent = thumb.alt || '';
            if (position) position.textContent = (active + 1) + ' / ' + angleTriggers.length;
        };
        angleTriggers.forEach((button, index) => button.addEventListener('click', () => {
            opener = button;
            show(index);
            document.querySelectorAll('dialog[open]').forEach(other => other.close());
            angleDialog.showModal();
            document.body.classList.add('is-locked');
            angleDialog.querySelector('[data-angle-close]')?.focus({preventScroll:true});
        }));
        angleDialog.querySelector('[data-angle-close]')?.addEventListener('click', () => angleDialog.close());
        angleDialog.querySelector('[data-angle-prev]')?.addEventListener('click', () => show(active - 1));
        angleDialog.querySelector('[data-angle-next]')?.addEventListener('click', () => show(active + 1));
        angleDialog.addEventListener('keydown', event => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            show(active + (event.key === 'ArrowLeft' ? -1 : 1));
        });
        angleDialog.addEventListener('click', event => {
            if (event.target !== angleDialog) return;
            const rect = angleDialog.getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) angleDialog.close();
        });
        angleDialog.addEventListener('close', () => {
            large?.removeAttribute('srcset');
            large?.removeAttribute('src');
            document.body.classList.remove('is-locked');
            if (opener?.isConnected) opener.focus({preventScroll:true});
        });
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                if (!reduceMotion.matches && typeof entry.target.animate === 'function') {
                    entry.target.animate([{opacity: 0.88, transform: 'translateY(10px)'}, {opacity: 1, transform: 'translateY(0)'}], {duration: 700, easing: 'cubic-bezier(.22,1,.36,1)'});
                }
            });
        }, {threshold: 0.12});
        document.querySelectorAll('.section-heading,.card-copy,.experience-copy,.arrivals-copy,.spread-copy').forEach(node => observer.observe(node));
    }
    const readJson = async response => {
        const contentType = response.headers.get('content-type') || '';
        return contentType.includes('application/json') ? response.json() : {};
    };
    const refreshTokens = async form => {
        if (form.dataset.inquiriesAvailable !== 'true' || form.querySelector('.form-controls')?.disabled) return false;
        const url = new URL(form.dataset.tokenUrl, window.location.href);
        if (url.origin !== window.location.origin) throw new Error();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}, signal: controller.signal});
            const data = await readJson(response);
            if (response.status === 403 || data.inquiries_allowed === false) return false;
            if (!response.ok || data.inquiries_allowed !== true || typeof data.csrf_token !== 'string' || !data.csrf_token || typeof data.form_token !== 'string' || !data.form_token) throw new Error();
            document.querySelectorAll('[data-enquiry-form]').forEach(candidate => {
                if (candidate.dataset.inquiriesAvailable !== 'true' || candidate.querySelector('.form-controls')?.disabled) return;
                candidate.elements.namedItem('_token').value = data.csrf_token;
                candidate.elements.namedItem('form_token').value = data.form_token;
            });
            const privacyForm = document.querySelector('[data-privacy-form]');
            if (privacyForm?.dataset.privacyAvailable === 'true' && !privacyForm.querySelector('.form-controls')?.disabled && data.privacy_requests_allowed === true && typeof data.privacy_form_token === 'string' && data.privacy_form_token) {
                privacyForm.elements.namedItem('_token').value = data.csrf_token;
                privacyForm.elements.namedItem('form_token').value = data.privacy_form_token;
            }
            return true;
        } finally {
            clearTimeout(timeout);
        }
    };
    document.querySelectorAll('[data-enquiry-form]').forEach(form => {
        const controls = form.querySelector('.form-controls');
        const available = form.dataset.inquiriesAvailable === 'true' && controls && !controls.disabled;
        if (!available) {
            form.addEventListener('submit', event => event.preventDefault());
            return;
        }
        const status = form.querySelector('[data-form-status]');
        const submit = form.querySelector('[type="submit"]');
        const reload = form.querySelector('[data-form-reload]');
        if (!status || !submit) return;
        const originalButton = Array.from(submit.childNodes).map(node => node.cloneNode(true));
        const restoreButton = () => submit.replaceChildren(...originalButton.map(node => node.cloneNode(true)));
        const announce = (message, focus = false) => {
            status.textContent = message;
            if (focus) {
                status.setAttribute('tabindex', '-1');
                status.focus();
            }
        };
        form.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                const error = form.querySelector(`[data-field-error="${CSS.escape(input.name)}"]`);
                if (error) error.textContent = '';
            });
            input.addEventListener('invalid', () => {
                const text = input.validity.typeMismatch && input.type === 'email' ? ui.invalid_email : input.validity.tooLong ? ui.too_long : ui.required;
                input.setCustomValidity(text);
                input.setAttribute('aria-invalid', 'true');
                const error = form.querySelector(`[data-field-error="${CSS.escape(input.name)}"]`);
                if (error) error.textContent = text;
            });
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!available || controls.disabled || form.getAttribute('aria-busy') === 'true' || submit.disabled) return;
            form.querySelectorAll('[data-field-error]').forEach(node => { node.textContent = ''; });
            form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
            status.textContent = '';
            if (reload) reload.hidden = true;
            submit.disabled = true;
            submit.textContent = ui.sending;
            form.setAttribute('aria-busy', 'true');
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);
            let completed = false;
            let blocked = false;
            try {
                const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'Accept': 'application/json'}, signal: controller.signal});
                const data = await readJson(response);
                if (response.status === 419) {
                    announce(ui.form_expired);
                    try {
                        const refreshed = await refreshTokens(form);
                        blocked = !refreshed;
                        announce(refreshed ? ui.form_refreshed : ui.forms_unavailable, true);
                    } catch {
                        blocked = true;
                        announce(ui.form_refresh_failed, true);
                        if (reload) reload.hidden = false;
                    }
                    return;
                }
                if (response.status === 403) {
                    blocked = true;
                    announce(ui.forms_unavailable, true);
                    return;
                }
                if (response.status === 429) {
                    announce(typeof data.message === 'string' ? data.message : ui.retry_after, true);
                    return;
                }
                if (response.status === 422 && data.errors && typeof data.errors === 'object') {
                    let focused = false;
                    const generalErrors = [];
                    Object.entries(data.errors).forEach(([key, errors]) => {
                        const input = form.elements.namedItem(key);
                        const message = Array.isArray(errors) ? errors.find(value => typeof value === 'string') : typeof errors === 'string' ? errors : '';
                        if (!message) return;
                        const node = form.querySelector(`[data-field-error="${CSS.escape(key)}"]`);
                        const visibleInput = input && input.type !== 'hidden' && !input.closest('.honeypot') && typeof input.focus === 'function';
                        if (visibleInput && node) {
                            node.textContent = message;
                            input.setAttribute('aria-invalid', 'true');
                            if (!focused) { input.focus(); focused = true; }
                        } else {
                            generalErrors.push(message);
                        }
                    });
                    if (generalErrors.length) {
                        blocked = true;
                        if (reload) reload.hidden = false;
                    }
                    announce(generalErrors.length ? generalErrors.join(' ') : typeof data.message === 'string' ? data.message : ui.try_again, !focused);
                    return;
                }
                if (!response.ok) throw new Error();
                if (typeof data.reference !== 'string' || !data.reference || data.booking_confirmed !== false) throw new Error();
                announce(`${ui.received} ${ui.reference}: ${data.reference}`, true);
                form.querySelectorAll('input').forEach(input => { input.disabled = true; });
                completed = true;
            } catch {
                announce(ui.try_again, true);
            } finally {
                clearTimeout(timeout);
                form.removeAttribute('aria-busy');
                restoreButton();
                submit.disabled = completed || blocked || !available || controls.disabled;
            }
        });
    });
    document.querySelectorAll('[data-privacy-form]').forEach(form => {
        const available = form.dataset.privacyAvailable === 'true';
        form.addEventListener('submit', event => {
            if (!available || form.querySelector('.form-controls')?.disabled) event.preventDefault();
        });
    });
})();
