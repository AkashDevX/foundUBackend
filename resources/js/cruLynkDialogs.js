import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const BRAND_PRIMARY = '#003d7a';
const BRAND_DANGER = '#b91c1c';

let booted = false;

const dialog = Swal.mixin({
    buttonsStyling: true,
    confirmButtonColor: BRAND_PRIMARY,
    cancelButtonColor: '#94a3b8',
    color: '#111827',
    customClass: {
        popup: 'crulynk-swal-popup',
        title: 'crulynk-swal-title',
        htmlContainer: 'crulynk-swal-text',
        confirmButton: 'crulynk-swal-confirm',
        cancelButton: 'crulynk-swal-cancel',
    },
    backdrop: 'rgba(0, 40, 85, 0.42)',
});

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4200,
    timerProgressBar: true,
    customClass: {
        popup: 'crulynk-swal-toast',
        title: 'crulynk-swal-toast-title',
    },
    didOpen: (popup) => {
        popup.addEventListener('mouseenter', Swal.stopTimer);
        popup.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * @param {{
 *   title?: string,
 *   text?: string,
 *   html?: string,
 *   confirmText?: string,
 *   cancelText?: string,
 *   icon?: 'warning' | 'question' | 'info' | 'error' | 'success',
 *   danger?: boolean,
 *   showCancel?: boolean,
 * }} options
 * @returns {Promise<boolean>}
 */
export async function confirmAction({
    title = 'Are you sure?',
    text = '',
    html = '',
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    icon = 'warning',
    danger = false,
    showCancel = true,
} = {}) {
    const result = await dialog.fire({
        title,
        text: html ? undefined : text,
        html: html || undefined,
        icon,
        showCancelButton: showCancel,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        focusCancel: showCancel,
        reverseButtons: showCancel,
        confirmButtonColor: danger ? BRAND_DANGER : BRAND_PRIMARY,
    });

    return result.isConfirmed === true;
}

/**
 * @param {{
 *   title?: string,
 *   text?: string,
 *   html?: string,
 *   icon?: 'warning' | 'question' | 'info' | 'error' | 'success',
 *   confirmText?: string,
 * }} options
 */
export async function alertDialog({
    title = 'Notice',
    text = '',
    html = '',
    icon = 'info',
    confirmText = 'OK',
} = {}) {
    await dialog.fire({
        title,
        text: html ? undefined : text,
        html: html || undefined,
        icon,
        confirmButtonText: confirmText,
        confirmButtonColor: BRAND_PRIMARY,
    });
}

export function toastSuccess(message) {
    if (!message) {
        return;
    }

    toast.fire({
        icon: 'success',
        title: message,
        iconColor: BRAND_PRIMARY,
    });
}

export function toastError(message) {
    if (!message) {
        return;
    }

    toast.fire({
        icon: 'error',
        title: message,
        iconColor: BRAND_DANGER,
    });
}

export function toastWarning(message) {
    if (!message) {
        return;
    }

    toast.fire({
        icon: 'warning',
        title: message,
        iconColor: '#d97706',
    });
}

export async function alertValidationErrors(title, errors) {
    const items = Array.isArray(errors) ? errors.filter(Boolean) : [];
    if (items.length === 0) {
        return;
    }

    if (items.length === 1) {
        await alertDialog({
            title,
            text: items[0],
            icon: 'error',
            confirmText: 'OK',
        });

        return;
    }

    const html = `<ul class="crulynk-swal-error-list">${items
        .map((item) => `<li>${escapeHtml(item)}</li>`)
        .join('')}</ul>`;

    await alertDialog({
        title,
        html,
        icon: 'error',
        confirmText: 'OK',
    });
}

function readFlashMeta(name) {
    const meta = document.querySelector(`meta[name="${name}"]`);
    if (!(meta instanceof HTMLMetaElement)) {
        return null;
    }

    const value = meta.content.trim();

    return value !== '' ? value : null;
}

function initPageFlashElements() {
    document.querySelectorAll('[data-flash-status]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        toastSuccess(element.dataset.flashStatus);
        element.remove();
    });

    document.querySelectorAll('[data-flash-error]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        toastError(element.dataset.flashError);
        element.remove();
    });

    document.querySelectorAll('[data-flash-warning]').forEach((element) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        toastWarning(element.dataset.flashWarning);
        element.remove();
    });
}

function readValidationPayload() {
    const payloadEl = document.getElementById('portal-validation-payload');
    if (payloadEl instanceof HTMLScriptElement) {
        try {
            const parsed = JSON.parse(payloadEl.textContent || '{}');
            payloadEl.remove();

            const errors = Array.isArray(parsed.errors) ? parsed.errors.filter(Boolean) : [];
            const title =
                typeof parsed.title === 'string' && parsed.title.trim() !== ''
                    ? parsed.title.trim()
                    : 'Please fix the following';

            if (errors.length > 0) {
                return { title, errors };
            }
        } catch {
            payloadEl.remove();
        }
    }

    const element = document.querySelector('[data-validation-errors]');
    if (!(element instanceof HTMLElement)) {
        return null;
    }

    let errors = [];
    try {
        errors = JSON.parse(element.dataset.validationErrors || '[]');
    } catch {
        errors = [];
    }

    const title = element.dataset.validationTitle || 'Please fix the following';
    element.remove();

    if (!Array.isArray(errors) || errors.length === 0) {
        return null;
    }

    return { title, errors: errors.filter(Boolean) };
}

function showValidationDialog(title, errors, isLoginPage) {
    if (errors.length === 0) {
        return;
    }

    if (isLoginPage && errors.length === 1) {
        alertDialog({
            title,
            text: errors[0],
            icon: 'error',
            confirmText: 'Try again',
        }).then(() => {
            const focusTarget =
                document.getElementById('password') ??
                document.getElementById('email') ??
                document.getElementById('company_id');
            focusTarget?.focus();
        });

        return;
    }

    alertValidationErrors(title, errors);
}

function initValidationErrors() {
    if (window.__portalValidationShown) {
        const payloadEl = document.getElementById('portal-validation-payload');
        payloadEl?.remove();

        return;
    }

    const payload = readValidationPayload();
    if (payload === null) {
        return;
    }

    window.__portalValidationShown = true;

    const isLoginPage = document.querySelector('[data-portal-login]') !== null;
    showValidationDialog(payload.title, payload.errors, isLoginPage);
}

function initFlashToasts() {
    initPageFlashElements();
    toastSuccess(readFlashMeta('flash-success'));
    toastError(readFlashMeta('flash-error'));
    toastSuccess(readFlashMeta('flash-status'));
}

function isDeleteForm(form) {
    if (form.dataset.confirmDanger === '1' || form.dataset.confirmDanger === 'true') {
        return true;
    }

    const methodInput = form.querySelector('input[name="_method"]');

    return methodInput instanceof HTMLInputElement && methodInput.value.toUpperCase() === 'DELETE';
}

function confirmConfigForForm(form) {
    if (form.dataset.noConfirm === '1' || form.dataset.noConfirm === 'true') {
        return null;
    }

    if (form.dataset.confirm) {
        return {
            title: form.dataset.confirmTitle || 'Are you sure?',
            text: form.dataset.confirm,
            confirmText: form.dataset.confirmConfirm || 'Confirm',
            cancelText: form.dataset.confirmCancel || 'Cancel',
            icon: form.dataset.confirmIcon || 'warning',
            danger: form.dataset.confirmDanger === '1' || form.dataset.confirmDanger === 'true',
        };
    }

    if (isDeleteForm(form)) {
        return {
            title: form.dataset.confirmTitle || 'Delete this item?',
            text: form.dataset.deleteConfirm || 'This action cannot be undone.',
            confirmText: form.dataset.confirmConfirm || 'Delete',
            cancelText: form.dataset.confirmCancel || 'Cancel',
            icon: 'warning',
            danger: true,
        };
    }

    return null;
}

function initConfirmForms() {
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const config = confirmConfigForForm(form);
            if (!config) {
                return;
            }

            if (form.dataset.confirmBypass === '1') {
                delete form.dataset.confirmBypass;

                return;
            }

            event.preventDefault();
            event.stopPropagation();

            confirmAction(config).then((confirmed) => {
                if (!confirmed) {
                    return;
                }

                form.dataset.confirmBypass = '1';

                const submitter = event.submitter instanceof HTMLElement ? event.submitter : undefined;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                } else {
                    form.submit();
                }
            });
        },
        true,
    );
}

function initConfirmButtons() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-confirm]') : null;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        if (button.tagName === 'FORM') {
            return;
        }

        if (button.dataset.confirmHandled === '1') {
            delete button.dataset.confirmHandled;

            return;
        }

        if (button.tagName !== 'BUTTON' && button.tagName !== 'A') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const formId = button.dataset.confirmForm;
        const danger = button.dataset.confirmDanger === '1' || button.dataset.confirmDanger === 'true';

        confirmAction({
            title: button.dataset.confirmTitle || 'Are you sure?',
            text: button.dataset.confirm || '',
            confirmText: button.dataset.confirmConfirm || 'Confirm',
            cancelText: button.dataset.confirmCancel || 'Cancel',
            icon: button.dataset.confirmIcon || 'warning',
            danger,
        }).then((confirmed) => {
            if (!confirmed) {
                return;
            }

            if (formId) {
                const form = document.getElementById(formId);
                if (form instanceof HTMLFormElement) {
                    form.requestSubmit?.() ?? form.submit();

                    return;
                }
            }

            button.dataset.confirmHandled = '1';
            button.click();
        });
    });
}

export function initCruLynkDialogs() {
    if (booted) {
        return;
    }

    booted = true;
    initConfirmForms();
    initConfirmButtons();
    initFlashToasts();
    initValidationErrors();
}

export const CruLynkDialog = {
    confirm: confirmAction,
    alert: alertDialog,
    toastSuccess,
    toastError,
    toastWarning,
    alertValidationErrors,
};

if (typeof window !== 'undefined') {
    window.CruLynkDialog = CruLynkDialog;

    const boot = () => initCruLynkDialogs();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}
