import { togglePanel } from './slide-panel';

/**
 * Generic "are you sure?" confirmation dialog, reusable from any feature on
 * any page (see the singleton <x-confirm-modal id="confirm-action"> in
 * layouts/app.blade.php). Call askConfirmation() instead of building a
 * one-off modal per feature:
 *
 *   askConfirmation({
 *       message: 'Affecter Jean Dupont à la classe CI — A ?',
 *       confirmLabel: 'Confirmer',
 *       onConfirm: () => { ... },
 *       onCancel: () => { ... }, // optional — also fires on ✕ / overlay click
 *   });
 *
 * Pass `danger: true` for destructive/risky actions (delete, archive, change
 * classe) — the modal's title/message turn red, a big ⚠️ appears above the
 * message, and the confirm button switches from `.btn.dark` to `.btn.danger`,
 * visually flagging that this one deserves a second look. See
 * resources/js/confirm-submit-form.js for the reusable "data-confirm-submit"
 * form wiring that uses this.
 *
 * Only one confirmation can be pending at a time per modalId (default
 * "confirm-action"), which matches how it's used in practice (a single
 * user-driven action awaiting a yes/no).
 */
const pending = {};

export function askConfirmation({
    message,
    title,
    confirmLabel = 'Confirmer',
    danger = false,
    onConfirm,
    onCancel,
    modalId = 'confirm-action',
} = {}) {
    const messageEl = document.querySelector(`[data-confirm-message="${modalId}"]`);
    const confirmButton = document.querySelector(`[data-confirm-confirm="${modalId}"]`);
    const titleEl = document.querySelector(`[data-confirm-title="${modalId}"]`);
    const modalEl = document.querySelector(`.confirm-modal[data-panel="${modalId}"]`);
    const iconEl = document.querySelector(`[data-confirm-icon="${modalId}"]`);

    if (!messageEl || !confirmButton) {
        // No confirm modal in the DOM (shouldn't happen given the layout
        // singleton, but fail safe rather than silently doing nothing).
        onConfirm?.();
        return;
    }

    messageEl.textContent = message ?? '';
    if (titleEl && title) {
        titleEl.textContent = title;
    }
    confirmButton.textContent = confirmLabel;
    confirmButton.classList.toggle('dark', !danger);
    confirmButton.classList.toggle('danger', danger);
    modalEl?.classList.toggle('confirm-modal--danger', danger);
    if (iconEl) {
        iconEl.style.display = danger ? 'block' : 'none';
    }

    pending[modalId] = { onConfirm, onCancel };

    togglePanel(modalId, true);
}

/**
 * Wires the (singleton, static) confirm/cancel/overlay/✕ controls once at
 * page load. Safe to call even if no <x-confirm-modal> is present.
 */
export function initConfirmModals() {
    document.querySelectorAll('[data-confirm-confirm]').forEach((button) => {
        const modalId = button.dataset.confirmConfirm;
        button.addEventListener('click', () => {
            togglePanel(modalId, false);
            pending[modalId]?.onConfirm?.();
            delete pending[modalId];
        });
    });

    document.querySelectorAll('[data-confirm-cancel]').forEach((button) => {
        const modalId = button.dataset.confirmCancel;
        button.addEventListener('click', () => runCancel(modalId));
    });

    document.querySelectorAll('.confirm-modal[data-panel]').forEach((modal) => {
        const modalId = modal.dataset.panel;
        modal.querySelector('.panel-close')?.addEventListener('click', () => runCancel(modalId));
        document.querySelector(`[data-panel-overlay="${modalId}"]`)?.addEventListener('click', () => runCancel(modalId));
    });
}

function runCancel(modalId) {
    pending[modalId]?.onCancel?.();
    delete pending[modalId];
}
