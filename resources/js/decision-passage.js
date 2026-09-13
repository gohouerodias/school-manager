/**
 * "Décisions de passage" page (resources/views/academique/annees/
 * decisions.blade.php) — same data-edit-*-trigger pattern as
 * academique-settings.js's initNiveauEdit(): the row's ✎ button carries the
 * apprenant's current moyenne/proposition/décision as data attributes, this
 * just copies them into the shared edit panel when clicked.
 */
export function initDecisionPassage() {
    const form = document.getElementById('edit-decision-form');
    if (!form) {
        return;
    }

    const eleveLabel = document.getElementById('edit-decision-eleve');
    const propositionLabel = document.getElementById('edit-decision-proposition');
    const decisionSelect = document.getElementById('edit-decision-select');
    const motifTextarea = document.getElementById('edit-decision-motif');
    const editUrlHidden = document.getElementById('edit-decision-edit-url');

    document.querySelectorAll('[data-edit-decision-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }

            eleveLabel.textContent = trigger.dataset.editEleve ?? '—';
            const moyenne = trigger.dataset.editMoyenne ?? '—';
            const proposition = trigger.dataset.editProposition;
            propositionLabel.textContent = proposition
                ? `${moyenne}/20 — ${proposition === 'admis' ? 'Admis(e)' : 'Redouble'}`
                : `${moyenne}/20 — pas encore calculable (aucun bulletin mensuel validé)`;
            decisionSelect.value = trigger.dataset.editDecision || proposition || '';
            motifTextarea.value = trigger.dataset.editMotif ?? '';
        });
    });
}
