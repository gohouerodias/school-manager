import { askConfirmation } from './confirm-modal';
import { refreshDropdownSelect } from './dropdown-select';

/**
 * Makes the "Classe" column of the élèves list (resources/views/eleves/
 * partials/table.blade.php) editable: picking a different classe from the
 * dropdown doesn't submit immediately — it opens the shared confirmation
 * modal (confirm-modal.js) first, and only actually changes the élève's
 * classe (via a hidden singleton PATCH form, eleves.classe.update) once the
 * user confirms. Cancelling reverts the select back to its previous value.
 *
 * The change listener is delegated on `document` rather than bound to each
 * select at init time, since the élèves list's live search (live-search.js)
 * replaces the whole table region's innerHTML on every keystroke — a
 * one-time querySelectorAll().forEach(addEventListener) would silently stop
 * working for freshly-rendered rows, the same class of bug already fixed
 * for action-menu.js / eleve-fiche.js / eleve-edit.js.
 *
 * Each select's *last confirmed* value is tracked in a data attribute
 * (rather than assumed from the DOM's current .value) so a cancelled change
 * can be reverted precisely, and so a genuine no-op (re-selecting the same
 * classe) doesn't pop the modal at all. markClasseAssignSelects() sets this
 * baseline once for every select present — called at init and again after
 * every live-search swap (see live-search.js), since freshly-swapped-in
 * selects wouldn't have it yet.
 */
export function initEleveClasseAssign() {
    markClasseAssignSelects(document);

    document.addEventListener('change', (event) => {
        const select = event.target.closest('.classe-assign-select');
        if (!select) {
            return;
        }

        const newValue = select.value;
        const previousValue = select.dataset.confirmedValue ?? '';

        if (newValue === previousValue) {
            return;
        }

        const eleveNom = select.dataset.eleveNom ?? 'cet apprenant';
        const newLabel = (select.options[select.selectedIndex]?.textContent ?? '').trim();

        askConfirmation({
            title: 'Modifier la classe',
            message: `Confirmer l'affectation de ${eleveNom} à « ${newLabel} » ?`,
            confirmLabel: 'Confirmer',
            onConfirm: () => submitClasseChange(select, newValue),
            onCancel: () => {
                select.value = previousValue;
                refreshDropdownSelect(select);
            },
        });
    });
}

/**
 * Records each select's current value as its "confirmed" baseline. Safe to
 * call repeatedly (e.g. after every live-search swap) — it simply
 * re-captures whatever the server just rendered as selected.
 */
export function markClasseAssignSelects(container = document) {
    container.querySelectorAll('.classe-assign-select').forEach((select) => {
        select.dataset.confirmedValue = select.value;
    });
}

function submitClasseChange(select, classeId) {
    const form = document.getElementById('classe-assign-form');
    const classeIdInput = document.getElementById('classe-assign-classe-id');
    if (!form || !classeIdInput) {
        return;
    }

    form.action = select.dataset.updateUrl;
    classeIdInput.value = classeId;
    form.submit();
}
