(() => {
    const apply = theme => {
        document.documentElement.dataset.theme = theme;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'light' ? '#F7F7F5' : '#0B0B0B');
        document.querySelectorAll('[data-recovery-theme-toggle]').forEach(button => button.setAttribute('aria-pressed', String(theme === 'light')));
    };
    let theme = 'dark';
    try { if (localStorage.getItem('londra-theme') === 'light') theme = 'light'; } catch {}
    apply(theme);
    const initialize = () => {
        const buttons = document.querySelectorAll('[data-recovery-theme-toggle]');
        if (!buttons.length) return;
        document.documentElement.classList.add('recovery-enhanced');
        buttons.forEach(button => button.addEventListener('click', () => {
            const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
            try { localStorage.setItem('londra-theme', next); } catch {}
            apply(next);
        }));
        apply(document.documentElement.dataset.theme);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
