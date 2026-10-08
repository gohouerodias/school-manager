import { askConfirmation } from './confirm-modal';
import { refreshDropdownSelect } from './dropdown-select';
import { togglePanel } from './slide-panel';

/**
 * Same pattern as eleve-classe-assign.js, applied to the "Statut" column:
 * picking "Actif"/"Archivé" from the dropdown opens the shared confirmation
 * modal before actually submitting, and reverts the select on cancel. No new
 * backend endpoint is needed — it submits to the existing eleves.archiver /
 * eleves.desarchiver routes (already used by the action-menu's "Archiver" /
 * "Désarchiver" buttons), just picking whichever one matches the newly
 * selected option.
 *
 * The change listener is delegated on `document` (not bound per-select at
 * init time) for the same reason as eleve-classe-assign.js: rows are
 * replaced wholesale by the élèves list's live search.
 */
export function initEleveStatutAssign() {
    markStatutAssignSelects(document);
    initArchiveMotifCancel();

    document.addEventListener('change', (event) => {
        const select = event.target.closest('.statut-assign-select');
        if (!select) {
            return;
        }

        const newValue = select.value;
        const previousValue = select.dataset.confirmedValue ?? 'actif';

        if (newValue === previousValue) {
            return;
        }

        const eleveNom = select.dataset.eleveNom ?? 'cet apprenant';
        const isArchiving = newValue === 'archive';

        // Archiver demande un motif : panneau dédié plutôt que la simple
        // confirmation (voir <x-slide-panel id="archive-motif">).
        if (isArchiving && document.getElementById('archive-motif-form')) {
            openArchiveMotifPanel(select, eleveNom, previousValue);
            return;
        }

        askConfirmation({
            title: isArchiving ? 'Archiver la fiche' : 'Désarchiver la fiche',
            message: isArchiving
                ? `Archiver la fiche de ${eleveNom} ? Elle restera consultable, mais ne pourra plus être modifiée tant qu'elle n'est pas désarchivée.`
                : `Désarchiver la fiche de ${eleveNom} ?`,
            confirmLabel: isArchiving ? 'Archiver' : 'Désarchiver',
            danger: isArchiving,
            onConfirm: () => submitStatutChange(select, isArchiving),
            onCancel: () => {
                select.value = previousValue;
                refreshDropdownSelect(select);
            },
        });
    });
}

/**
 * Records each select's current value as its "confirmed" baseline. Safe to
 * call repeatedly (e.g. after every live-search swap) — see
 * eleve-classe-assign.js's markClasseAssignSelects() for the same idea.
 */
export function markStatutAssignSelects(container = document) {
    container.querySelectorAll('.statut-assign-select').forEach((select) => {
        select.dataset.confirmedValue = select.value;
    });
}

let pendingArchiveSelect = null;

function openArchiveMotifPanel(select, eleveNom, previousValue) {
    const form = document.getElementById('archive-motif-form');
    const input = document.getElementById('archive-motif-input');
    const intro = document.getElementById('archive-motif-intro');

    form.action = select.dataset.archiverUrl;
    input.value = '';
    if (intro) {
        intro.textContent = `Pourquoi archiver la fiche de ${eleveNom} ?`;
    }

    pendingArchiveSelect = { select, previousValue };
    togglePanel('archive-motif', true);
    setTimeout(() => input.focus(), 50);
}

/**
 * Fermer le panneau sans archiver (Annuler, ✕, clic sur le fond) remet le
 * statut affiché à sa valeur précédente.
 */
function initArchiveMotifCancel() {
    const form = document.getElementById('archive-motif-form');
    if (!form) {
        return;
    }

    let envoi = false;
    form.addEventListener('submit', () => {
        envoi = true;
    });

    document.querySelectorAll('[data-panel-close="archive-motif"], [data-panel-overlay="archive-motif"]').forEach((el) => {
        el.addEventListener('click', () => {
            if (envoi || !pendingArchiveSelect) {
                return;
            }
            pendingArchiveSelect.select.value = pendingArchiveSelect.previousValue;
            refreshDropdownSelect(pendingArchiveSelect.select);
            pendingArchiveSelect = null;
        });
    });
}

function submitStatutChange(select, isArchiving) {
    const form = document.getElementById('statut-assign-form');
    if (!form) {
        return;
    }

    form.action = isArchiving ? select.dataset.archiverUrl : select.dataset.desarchiverUrl;
    form.submit();
}
