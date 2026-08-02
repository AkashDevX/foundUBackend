/**
 * On POST forms inside main, show a busy state on the submitter so the UI
 * does not look frozen. Opt out with `data-skip-form-busy` on the form.
 */
function findSubmitButton(form) {
    const s = form.querySelector('button[type="submit"]');
    if (s instanceof HTMLButtonElement) {
        return s;
    }
    const i = form.querySelector('input[type="submit"]');
    if (i instanceof HTMLInputElement) {
        return i;
    }
    return null;
}

function setInputBusy(input) {
    if (!input.dataset.wfLabelSaved) {
        input.dataset.wfLabelSaved = input.value;
    }
    input.setAttribute('aria-busy', 'true');
    input.disabled = true;
    input.classList.add('opacity-75', 'pointer-events-none');
    input.value = 'Submitting…';
}

function setButtonBusy(button) {
    if (!button.dataset.wfLabelSaved) {
        button.dataset.wfLabelSaved = button.innerHTML;
    }
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.classList.add('pointer-events-none', 'opacity-75');
    const wrap = document.createElement('span');
    wrap.className = 'inline-flex w-full items-center justify-center gap-2';
    wrap.setAttribute('aria-hidden', 'true');
    wrap.innerHTML = `<svg class="size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-80" />
        </svg>
        <span>Submitting…</span>`;
    button.textContent = '';
    button.appendChild(wrap);
}

function init() {
    const main = document.querySelector('main');
    if (!main) {
        return;
    }

    main.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post' && method !== 'dialog') {
            return;
        }
        if (form.dataset.skipFormBusy === '1' || form.hasAttribute('data-skip-form-busy')) {
            return;
        }

        const sub = e.submitter;
        let el = null;
        if (sub instanceof HTMLButtonElement && sub.type === 'submit' && !sub.disabled) {
            el = sub;
        } else if (sub instanceof HTMLInputElement && sub.type === 'submit' && !sub.disabled) {
            el = sub;
        } else {
            const fb = findSubmitButton(form);
            if (fb && !fb.disabled) {
                el = fb;
            }
        }
        if (!el) {
            return;
        }

        if (el instanceof HTMLInputElement) {
            setInputBusy(el);
        } else {
            setButtonBusy(el);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
