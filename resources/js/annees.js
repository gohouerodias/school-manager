/**
 * "Années académiques" index (resources/views/academique/annees/index.blade.php) :
 * seules les dates de début/fin sont modifiables (le libellé reste fixe une
 * fois créé) — même pattern data-edit-*-trigger que examens.js's
 * initExamenEdit().
 */
export function initAnneeEdit() {
    const form = document.getElementById('edit-annee-form');
    if (!form) {
        return;
    }

    const libelle = document.getElementById('edit-annee-libelle');
    const dateDebutInput = document.getElementById('edit-annee-date-debut');
    const dateFinInput = document.getElementById('edit-annee-date-fin');
    const editUrlHidden = document.getElementById('edit-annee-edit-url');

    document.querySelectorAll('[data-edit-annee-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }

            libelle.textContent = trigger.dataset.editLibelle ?? '—';
            dateDebutInput.value = trigger.dataset.editDateDebut ?? '';
            dateFinInput.value = trigger.dataset.editDateFin ?? '';
        });
    });
}
