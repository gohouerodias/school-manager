import { askConfirmation } from './confirm-modal';
import { showPageLoader } from './page-loader';

/**
 * Wires any `<form data-confirm-submit>` to ask for confirmation through the
 * shared confirm-modal (see confirm-modal.js) before it actually submits,
 * instead of the browser's native `confirm()` popup. Reusable from any
 * feature just by naming the form's attributes — no per-form JS needed:
 *
 *   <form method="POST" action="..." data-confirm-submit
 *         data-confirm-title="Supprimer ce type de document"
 *         data-confirm-message="Supprimer ce type de document ?"
 *         data-confirm-danger="1">
 *
 * `data-confirm-danger="1"` renders the modal in its red, destructive-action
 * styling (see askConfirmation's `danger` option) — used for "supprimer" /
 * "archiver" forms.
 *
 * Delegated on `document`, so it also covers forms rendered dynamically from
 * JS templates (e.g. eleve-fiche.js's tuteur/document delete buttons),
 * without needing to re-wire listeners every time the fiche re-renders.
 */
export function initConfirmSubmitForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-confirm-submit]');

        if (!form) {
            return;
        }

        event.preventDefault();

        askConfirmation({
            title: form.dataset.confirmTitle,
            message: form.dataset.confirmMessage || 'Confirmer cette action ?',
            confirmLabel: form.dataset.confirmLabel || 'Confirmer',
            danger: form.dataset.confirmDanger === '1',
            onConfirm: () => {
                showPageLoader();
                // form.submit() (unlike requestSubmit()) never fires another
                // 'submit' event, so this doesn't loop back into this same
                // listener.
                form.submit();
            },
        });
    });
}
