(() => {
    'use strict';
    let ui = {};
    try { ui = JSON.parse(document.body.dataset.ui || '{}'); } catch { ui = {}; }
    const root = document.documentElement;
    root.classList.add('js');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    /* Appearance */
    const themeButton = document.querySelector('[data-theme-toggle]');
    const syncTheme = () => {
        const light = root.dataset.theme === 'light';
        themeButton?.setAttribute('aria-pressed', String(light));
        if (ui.light) themeButton?.setAttribute('aria-label', ui.light);
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', light ? '#F7F7F5' : '#0B0B0B');
    };
    themeButton?.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'light' ? 'dark' : 'light';
        try { localStorage.setItem('londra-theme', root.dataset.theme); } catch { /* Storage unavailable */ }
        syncTheme();
    });
    syncTheme();

    /* Header state */
    const header = document.querySelector('[data-site-header]');
    if (header) {
        let ticking = false;
        const update = () => { header.classList.toggle('is-scrolled', window.scrollY > 8); ticking = false; };
        window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, {passive: true});
        update();
    }

    /* Disclosures that close on Escape or an outside click */
    const closeLanguageMenus = except => document.querySelectorAll('[data-language-switch][open]').forEach(menu => { if (menu !== except) menu.open = false; });
    document.querySelectorAll('[data-language-switch]').forEach(menu => {
        menu.addEventListener('toggle', () => { if (menu.open) { closeNavPanels(); closeLanguageMenus(menu); } });
        menu.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !menu.open) return;
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    });
    const navPanels = Array.from(document.querySelectorAll('[data-nav-panel]'));
    const setPanel = (item, open) => {
        const toggle = item.querySelector('[data-nav-panel-toggle]');
        const panel = item.querySelector('[data-nav-panel-content]');
        if (!toggle || !panel) return;
        toggle.setAttribute('aria-expanded', String(open));
        panel.classList.toggle('is-open', open);
    };
    function closeNavPanels(except) { navPanels.forEach(item => { if (item !== except) setPanel(item, false); }); }
    navPanels.forEach(item => {
        const toggle = item.querySelector('[data-nav-panel-toggle]');
        toggle?.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            closeNavPanels(item);
            closeLanguageMenus();
            setPanel(item, open);
        });
        item.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || toggle?.getAttribute('aria-expanded') !== 'true') return;
            setPanel(item, false);
            toggle.focus();
        });
        item.addEventListener('focusout', event => { if (!item.contains(event.relatedTarget)) setPanel(item, false); });
    });
    document.addEventListener('click', event => {
        navPanels.forEach(item => { if (!item.contains(event.target)) setPanel(item, false); });
        document.querySelectorAll('[data-language-switch][open]').forEach(menu => { if (!menu.contains(event.target)) menu.open = false; });
    });

    /* Contents list: mark the section in view, natively where scroll-target-group is supported */
    const contents = document.querySelector('[data-contents]');
    if (contents) {
        const links = Array.from(contents.querySelectorAll('a[href^="#"]'));
        const keepVisible = link => {
            if (!link || contents.scrollHeight <= contents.clientHeight) return;
            const top = link.offsetTop;
            const bottom = top + link.offsetHeight;
            if (top < contents.scrollTop) contents.scrollTop = top - 8;
            else if (bottom > contents.scrollTop + contents.clientHeight) contents.scrollTop = bottom - contents.clientHeight + 8;
        };
        const native = CSS.supports('scroll-target-group: auto');
        const mark = current => {
            links.forEach(link => {
                if (!native) link.classList.toggle('target-current', link === current);
                if (link === current) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            });
            keepVisible(current);
        };
        if (native) {
            const sync = () => mark(contents.querySelector('a:target-current'));
            requestAnimationFrame(sync);
            window.addEventListener('load', () => requestAnimationFrame(sync), {once: true});
            document.addEventListener('scrollend', sync);
        } else if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) mark(links.find(link => link.hash === `#${entry.target.id}`) || null);
                });
            }, {rootMargin: '-45% 0px -55% 0px'});
            links.forEach(link => {
                const target = document.getElementById(link.hash.slice(1));
                if (target) observer.observe(target);
            });
        }
    }

    /* Dialogs: native modal behaviour, light dismissal where closedby is unsupported */
    const supportsClosedBy = 'closedBy' in HTMLDialogElement.prototype;
    const outside = (event, dialog) => {
        const rect = dialog.getBoundingClientRect();
        return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
    };
    const registerDialog = (id, openSelector, onOpen) => {
        const dialog = document.getElementById(id);
        if (!dialog || typeof dialog.showModal !== 'function') return;
        root.classList.add('has-dialogs');
        if (!supportsClosedBy) {
            let pressedOutside = false;
            dialog.addEventListener('pointerdown', event => { pressedOutside = outside(event, dialog); });
            dialog.addEventListener('pointerup', event => { if (pressedOutside && outside(event, dialog)) dialog.close(); pressedOutside = false; });
        }
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
        document.querySelectorAll(openSelector).forEach(trigger => {
            trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-controls', id);
            trigger.addEventListener('click', event => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                if (onOpen?.(dialog, trigger) === false) return;
                event.preventDefault();
                closeNavPanels();
                closeLanguageMenus();
                document.querySelectorAll('dialog[open]').forEach(other => { if (other !== dialog) other.close(); });
                if (!dialog.open) dialog.showModal();
                dialog.querySelector('[tabindex="-1"]')?.focus({preventScroll: true});
            });
        });
    };
    registerDialog('navigation-dialog', '[data-open-navigation]');
    registerDialog('enquiry-dialog', '[data-open-enquiry]', (dialog, trigger) => {
        const selected = trigger.dataset.service || '';
        const service = dialog.querySelector('[name="service_key"]');
        if (service) service.value = selected;
        const context = dialog.querySelector('[data-enquiry-context]');
        if (context) {
            const name = trigger.dataset.serviceName || '';
            context.hidden = !selected || !name;
            const label = context.querySelector('strong');
            if (label) label.textContent = name;
        }
    });
    registerDialog('compare-dialog', '[data-open-compare]');
    registerDialog('gallery-dialog', '[data-open-gallery]', (dialog, trigger) => {
        const source = trigger.closest('.fleet-panel')?.querySelector('.fleet-photo img');
        const container = dialog.querySelector('[data-gallery-photo]');
        if (!source || !container) return false;
        const image = source.cloneNode(true);
        image.setAttribute('sizes', '(min-width: 1232px) 1168px, calc(100vw - 64px)');
        image.removeAttribute('loading');
        image.removeAttribute('fetchpriority');
        container.replaceChildren(image);
        const title = dialog.querySelector('h2');
        const vehicle = trigger.dataset.vehicleName || '';
        if (title) title.textContent = vehicle ? `${ui.gallery_title} · ${vehicle}` : ui.gallery_title;
    });
    document.getElementById('gallery-dialog')?.addEventListener('close', event => {
        event.currentTarget.querySelector('[data-gallery-photo]')?.replaceChildren();
    });

    /* Fleet selector */
    document.querySelectorAll('[data-deck]').forEach(deck => {
        const tablist = deck.querySelector('[data-tablist]');
        const tabs = Array.from(deck.querySelectorAll('[data-tab]'));
        const panels = Array.from(deck.querySelectorAll('[data-panel]'));
        if (!tablist || !tabs.length || tabs.length !== panels.length) return;
        tablist.setAttribute('role', 'tablist');
        if (tablist.dataset.label) tablist.setAttribute('aria-label', tablist.dataset.label);
        tabs.forEach(tab => tab.setAttribute('role', 'tab'));
        panels.forEach(panel => { panel.setAttribute('role', 'tabpanel'); panel.tabIndex = 0; });
        let current = 0;
        const activate = (index, focus = false) => {
            current = (index + tabs.length) % tabs.length;
            tabs.forEach((tab, i) => {
                const selected = i === current;
                tab.setAttribute('aria-selected', String(selected));
                tab.tabIndex = selected ? 0 : -1;
                panels[i].hidden = !selected;
            });
            if (focus) tabs[current].focus();
        };
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(index));
            tab.addEventListener('keydown', event => {
                const keys = {ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1};
                if (!(event.key in keys)) return;
                event.preventDefault();
                activate(keys[event.key], true);
            });
        });
        deck.querySelector('[data-deck-prev]')?.addEventListener('click', () => activate(current - 1));
        deck.querySelector('[data-deck-next]')?.addEventListener('click', () => activate(current + 1));
        deck.classList.add('is-enhanced');
        activate(0);
    });

    /* Reveal on scroll */
    if ('IntersectionObserver' in window && !reduceMotion.matches) {
        const targets = document.querySelectorAll('.section-heading, .service-card, .split-copy, .split-media, .spread, .process-steps li, .destination-card, .faq-intro, .invitation-copy, .invitation-media, .airport-card, .directory-group, .intro-grid > *');
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {rootMargin: '0px 0px -8% 0px', threshold: 0.08});
        targets.forEach(node => {
            const rect = node.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) return;
            node.classList.add('reveal');
            observer.observe(node);
        });
    }

    /* Enquiry forms */
    const readJson = async response => ((response.headers.get('content-type') || '').includes('application/json') ? response.json() : {});
    const formUsable = form => form.dataset.inquiriesAvailable === 'true' && !form.querySelector('.form-controls')?.disabled;
    const refreshTokens = async form => {
        if (!formUsable(form)) return false;
        const url = new URL(form.dataset.tokenUrl, window.location.href);
        if (url.origin !== window.location.origin) throw new Error('Unexpected origin');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}, signal: controller.signal});
            const data = await readJson(response);
            if (response.status === 403 || data.inquiries_allowed === false) return false;
            if (!response.ok || data.inquiries_allowed !== true || typeof data.csrf_token !== 'string' || !data.csrf_token || typeof data.form_token !== 'string' || !data.form_token) throw new Error('Invalid token response');
            document.querySelectorAll('[data-enquiry-form]').forEach(candidate => {
                if (!formUsable(candidate)) return;
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
    const fieldError = (form, name) => form.querySelector(`[data-field-error="${CSS.escape(name)}"]`);
    const messageFor = input => (input.validity.typeMismatch && input.type === 'email' ? ui.invalid_email : input.validity.tooLong ? ui.too_long : ui.required);
    document.querySelectorAll('[data-enquiry-form]').forEach(form => {
        if (!formUsable(form)) {
            form.addEventListener('submit', event => event.preventDefault());
            return;
        }
        const controls = form.querySelector('.form-controls');
        const status = form.querySelector('[data-form-status]');
        const submit = form.querySelector('[type="submit"]');
        const reload = form.querySelector('[data-form-reload]');
        if (!status || !submit || !controls) return;
        form.noValidate = true;
        const original = Array.from(submit.childNodes).map(node => node.cloneNode(true));
        const restoreButton = () => submit.replaceChildren(...original.map(node => node.cloneNode(true)));
        const announce = (message, focus = false) => {
            status.textContent = message;
            if (focus) { status.tabIndex = -1; status.focus(); }
        };
        const visibleInputs = Array.from(form.querySelectorAll('.form-controls input'));
        visibleInputs.forEach(input => input.addEventListener('input', () => {
            input.removeAttribute('aria-invalid');
            const error = fieldError(form, input.name);
            if (error) error.textContent = '';
        }));
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (controls.disabled || submit.disabled || form.getAttribute('aria-busy') === 'true') return;
            form.querySelectorAll('[data-field-error]').forEach(node => { node.textContent = ''; });
            form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
            status.textContent = '';
            if (reload) reload.hidden = true;
            const invalid = visibleInputs.filter(input => !input.checkValidity());
            if (invalid.length) {
                invalid.forEach(input => {
                    input.setAttribute('aria-invalid', 'true');
                    const error = fieldError(form, input.name);
                    if (error) error.textContent = messageFor(input);
                });
                invalid[0].focus();
                return;
            }
            submit.disabled = true;
            submit.textContent = ui.sending;
            form.setAttribute('aria-busy', 'true');
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);
            let completed = false;
            let blocked = false;
            try {
                const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: controller.signal});
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
                if (response.status === 403) { blocked = true; announce(ui.forms_unavailable, true); return; }
                if (response.status === 429) { announce(typeof data.message === 'string' ? data.message : ui.retry_after, true); return; }
                if (response.status === 422 && data.errors && typeof data.errors === 'object') {
                    let focused = false;
                    const general = [];
                    Object.entries(data.errors).forEach(([key, errors]) => {
                        const message = Array.isArray(errors) ? errors.find(value => typeof value === 'string') : typeof errors === 'string' ? errors : '';
                        if (!message) return;
                        const input = form.elements.namedItem(key);
                        const node = fieldError(form, key);
                        const visible = input instanceof HTMLInputElement && input.type !== 'hidden' && !input.closest('.honeypot');
                        if (visible && node) {
                            node.textContent = message;
                            input.setAttribute('aria-invalid', 'true');
                            if (!focused) { input.focus(); focused = true; }
                        } else {
                            general.push(message);
                        }
                    });
                    if (general.length) { blocked = true; if (reload) reload.hidden = false; }
                    announce(general.length ? general.join(' ') : typeof data.message === 'string' ? data.message : ui.try_again, !focused);
                    return;
                }
                if (!response.ok || typeof data.reference !== 'string' || !data.reference || data.booking_confirmed !== false) throw new Error('Unexpected response');
                announce(`${ui.received} ${ui.reference}: ${data.reference}`, true);
                visibleInputs.forEach(input => { input.disabled = true; });
                completed = true;
            } catch {
                announce(ui.try_again, true);
            } finally {
                clearTimeout(timeout);
                form.removeAttribute('aria-busy');
                restoreButton();
                submit.disabled = completed || blocked || controls.disabled;
            }
        });
    });
    document.querySelectorAll('[data-privacy-form]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.privacyAvailable !== 'true' || form.querySelector('.form-controls')?.disabled) event.preventDefault();
        });
    });
})();
