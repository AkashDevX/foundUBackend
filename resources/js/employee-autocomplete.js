/**
 * Client-side employee search autocomplete for admin task forms.
 */
function normalizeSearch(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function initEmployeeAutocomplete(root) {
    const hidden = root.querySelector('[data-employee-autocomplete-value]');
    const input = root.querySelector('[data-employee-autocomplete-input]');
    const suggestions = root.querySelector('[data-employee-autocomplete-suggestions]');
    const clearBtn = root.querySelector('[data-employee-autocomplete-clear]');

    if (!hidden || !input || !suggestions) {
        return;
    }

    let employees = [];
    try {
        employees = JSON.parse(root.dataset.employees || '[]');
    } catch {
        employees = [];
    }

    if (!Array.isArray(employees)) {
        employees = [];
    }

    suggestions.classList.add('absolute', 'left-0', 'right-0', 'top-full', 'z-30', 'mt-1', 'max-h-60', 'overflow-y-auto', 'rounded-xl', 'border', 'border-brand-border', 'bg-white', 'py-1', 'shadow-lg', 'ring-1', 'ring-black/10');

    let suppressBlurHide = false;
    let activeIndex = -1;

    function positionSuggestions() {
        // Suggestions sit directly below the input inside the relative wrapper.
    }

    function hideSuggestions() {
        suggestions.innerHTML = '';
        suggestions.classList.add('hidden');
        activeIndex = -1;
    }

    function showSuggestions() {
        positionSuggestions();
        suggestions.classList.remove('hidden');
    }

    function syncClearVisibility() {
        if (clearBtn) {
            clearBtn.classList.toggle('hidden', input.value.trim() === '');
        }
    }

    function selectEmployee(employee) {
        hidden.value = employee?.id || '';
        input.value = employee?.label || '';
        syncClearVisibility();
        hideSuggestions();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function clearSelection() {
        hidden.value = '';
        input.value = '';
        syncClearVisibility();
        hideSuggestions();
        input.focus();
    }

    function renderHint(message) {
        suggestions.innerHTML = '';
        const row = document.createElement('div');
        row.className = 'px-3 py-2.5 text-xs text-brand-text-secondary';
        row.textContent = message;
        suggestions.appendChild(row);
        showSuggestions();
    }

    function filterEmployees(query) {
        const normalized = normalizeSearch(query);
        if (normalized === '') {
            return employees.slice(0, 12);
        }

        return employees
            .filter((employee) => {
                const haystack = normalizeSearch(employee.search || employee.label || '');
                return haystack.includes(normalized);
            })
            .slice(0, 12);
    }

    function renderMatches(query) {
        const matches = filterEmployees(query);
        suggestions.innerHTML = '';

        if (matches.length === 0) {
            renderHint('No employees match that search.');
            return;
        }

        matches.forEach((employee, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className =
                'block w-full px-3 py-2.5 text-left text-sm text-brand-text transition hover:bg-brand-surface focus:bg-brand-surface focus:outline-none';
            button.dataset.index = String(index);
            button.innerHTML = `<span class="font-medium">${employee.label}</span>`;
            if (employee.email && employee.email !== employee.label) {
                button.innerHTML += `<span class="mt-0.5 block text-xs text-brand-text-secondary">${employee.email}</span>`;
            }
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                suppressBlurHide = true;
            });
            button.addEventListener('click', () => selectEmployee(employee));
            suggestions.appendChild(button);
        });

        showSuggestions();
    }

    function highlightActive() {
        const buttons = suggestions.querySelectorAll('button[data-index]');
        buttons.forEach((button, index) => {
            button.classList.toggle('bg-brand-surface', index === activeIndex);
        });
    }

    input.addEventListener('input', () => {
        hidden.value = '';
        syncClearVisibility();
        renderMatches(input.value);
    });

    input.addEventListener('focus', () => {
        renderMatches(input.value);
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (suppressBlurHide) {
                suppressBlurHide = false;
                return;
            }
            hideSuggestions();
        }, 150);
    });

    input.addEventListener('keydown', (event) => {
        const buttons = suggestions.querySelectorAll('button[data-index]');
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (buttons.length === 0) {
                renderMatches(input.value);
                return;
            }
            activeIndex = Math.min(activeIndex + 1, buttons.length - 1);
            highlightActive();
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
            highlightActive();
            return;
        }
        if (event.key === 'Enter' && activeIndex >= 0 && buttons[activeIndex]) {
            event.preventDefault();
            buttons[activeIndex].click();
            return;
        }
        if (event.key === 'Escape') {
            hideSuggestions();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => clearSelection());
    }

    syncClearVisibility();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-employee-autocomplete]').forEach((root) => {
        initEmployeeAutocomplete(root);
    });
});
