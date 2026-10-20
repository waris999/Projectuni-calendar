(() => {
    const sidebar = document.querySelector('body.app-layout > .sidebar');
    const topbar = document.querySelector('body.app-layout > .main > .topbar');
    if (!sidebar || !topbar) return;
    const media = window.matchMedia('(max-width: 767px)');
    sidebar.id = sidebar.id || 'app-sidebar';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-menu-button';
    button.textContent = '☰ เมนู';
    button.setAttribute('aria-controls', sidebar.id);
    button.setAttribute('aria-expanded', 'false');
    topbar.prepend(button);
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'mobile-menu-backdrop';
    backdrop.setAttribute('aria-label', 'ปิดเมนู');
    backdrop.tabIndex = -1;
    document.body.append(backdrop);
    function setOpen(open, restoreFocus = false) {
        const expanded = media.matches && open;
        document.body.classList.toggle('mobile-menu-open', expanded);
        button.setAttribute('aria-expanded', String(expanded));
        sidebar.inert = media.matches && !expanded;
        if (expanded) sidebar.querySelector('a, button')?.focus();
        else if (restoreFocus) button.focus();
    }
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    backdrop.addEventListener('click', () => setOpen(false, true));
    document.addEventListener('keydown', event => {
        if (button.getAttribute('aria-expanded') !== 'true') return;
        if (event.key === 'Escape') { setOpen(false, true); return; }
        if (event.key !== 'Tab') return;
        const elements = [button, ...sidebar.querySelectorAll('a[href], button:not([disabled])')];
        const current = elements.indexOf(document.activeElement);
        if (event.shiftKey && current === 0) { event.preventDefault(); elements.at(-1).focus(); }
        else if (!event.shiftKey && current === elements.length - 1) { event.preventDefault(); button.focus(); }
    });
    media.addEventListener('change', () => setOpen(false));
    setOpen(false);
    document.querySelectorAll('.main table').forEach(table => {
        if (table.closest('.hours-table-scroll, .mobile-table-scroll')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'mobile-table-scroll';
        wrapper.tabIndex = 0;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'ตารางข้อมูล เลื่อนซ้ายและขวาได้');
        table.before(wrapper);
        wrapper.append(table);
    });
})();
