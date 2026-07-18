document.addEventListener('DOMContentLoaded', () => {
    const filterToggle = document.querySelector('[data-timesheet-filter-toggle]');
    const filterPanel = document.querySelector('[data-timesheet-filter-panel]');
    const modal = document.getElementById('timesheet-fullscreen-modal');
    const modalPanel = document.querySelector('[data-timesheet-modal-panel]');
    const modalScroll = document.querySelector('[data-timesheet-modal-scroll]');

    if (filterToggle && filterPanel) {
        filterToggle.addEventListener('click', () => {
            const isHidden = filterPanel.classList.contains('hidden');
            filterPanel.classList.toggle('hidden', !isHidden);
            filterToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });
    }

    if (modal) {
        document.body.classList.add('overflow-hidden');

        const closeUrl = modal.getAttribute('data-close-url');
        const navigateToClose = () => {
            if (closeUrl) {
                window.location.href = closeUrl;
            }
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                navigateToClose();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                navigateToClose();
            }
        });

        modalPanel?.addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    if (modalScroll) {
        modalScroll.addEventListener(
            'wheel',
            (event) => {
                if (!event.shiftKey || modalScroll.scrollWidth <= modalScroll.clientWidth) {
                    return;
                }

                event.preventDefault();
                modalScroll.scrollLeft += event.deltaY;
            },
            { passive: false },
        );
    }
});
