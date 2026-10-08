document.addEventListener('DOMContentLoaded', () => {
    const menus = [
        ...document.querySelectorAll('[data-account-menu]'),
        ...document.querySelectorAll('[data-incident-menu]'),
    ];
    if (menus.length === 0) {
        return;
    }

    const toggleFor = (menu) => menu.querySelector('[data-account-menu-toggle], [data-incident-menu-toggle]');
    const panelFor = (menu) => menu.querySelector('[data-account-menu-panel], [data-incident-menu-panel]');

    const closeAll = (except = null) => {
        menus.forEach((menu) => {
            if (menu === except) {
                return;
            }
            const toggle = toggleFor(menu);
            const panel = panelFor(menu);
            if (panel) {
                panel.classList.add('hidden');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    };

    menus.forEach((menu) => {
        const toggle = toggleFor(menu);
        const panel = panelFor(menu);
        if (!toggle || !panel) {
            return;
        }

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = !panel.classList.contains('hidden');
            closeAll();
            if (!isOpen) {
                panel.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', () => closeAll());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });
});
