document.addEventListener('DOMContentLoaded', () => {
    const menus = document.querySelectorAll('[data-account-menu]');
    if (menus.length === 0) {
        return;
    }

    const closeAll = (except = null) => {
        menus.forEach((menu) => {
            if (menu === except) {
                return;
            }
            const toggle = menu.querySelector('[data-account-menu-toggle]');
            const panel = menu.querySelector('[data-account-menu-panel]');
            if (panel) {
                panel.classList.add('hidden');
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    };

    menus.forEach((menu) => {
        const toggle = menu.querySelector('[data-account-menu-toggle]');
        const panel = menu.querySelector('[data-account-menu-panel]');
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
