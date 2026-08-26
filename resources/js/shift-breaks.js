/**
 * Repeatable paid/unpaid break rows on organization shift forms.
 *
 * @param {ParentNode} root
 */
export function initShiftBreaksRepeaters(root = document) {
    root.querySelectorAll('[data-shift-breaks-repeater]').forEach((repeater) => {
        if (!(repeater instanceof HTMLElement) || repeater.dataset.bound === '1') {
            return;
        }
        repeater.dataset.bound = '1';

        const list = repeater.querySelector('[data-shift-breaks-list]');
        const template = repeater.querySelector('[data-shift-breaks-template]');
        const addBtn = repeater.querySelector('[data-shift-breaks-add]');
        const maxBreaks = Math.max(1, Number(repeater.dataset.maxBreaks || 8));

        if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
            return;
        }

        const reindex = () => {
            list.querySelectorAll('[data-shift-breaks-row]').forEach((row, index) => {
                row.querySelectorAll('input, select').forEach((field) => {
                    const name = field.getAttribute('name');
                    if (!name) return;
                    field.setAttribute(
                        'name',
                        name.replace(/shift_breaks\[(?:\d+|__INDEX__)]/, `shift_breaks[${index}]`),
                    );
                });
            });
            if (addBtn instanceof HTMLButtonElement) {
                addBtn.disabled = list.querySelectorAll('[data-shift-breaks-row]').length >= maxBreaks;
            }
        };

        addBtn?.addEventListener('click', () => {
            if (list.querySelectorAll('[data-shift-breaks-row]').length >= maxBreaks) {
                return;
            }
            const fragment = template.content.cloneNode(true);
            list.appendChild(fragment);
            reindex();
        });

        list.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const removeBtn = target.closest('[data-shift-breaks-remove]');
            if (!removeBtn) return;
            const row = removeBtn.closest('[data-shift-breaks-row]');
            if (!row) return;

            const rows = list.querySelectorAll('[data-shift-breaks-row]');
            if (rows.length <= 1) {
                row.querySelectorAll('input').forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.value = '';
                    }
                });
                const select = row.querySelector('select');
                if (select instanceof HTMLSelectElement) {
                    select.value = '0';
                }
                return;
            }

            row.remove();
            reindex();
        });

        reindex();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initShiftBreaksRepeaters(document);
    });
} else {
    initShiftBreaksRepeaters(document);
}
