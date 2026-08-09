/**
 * "Niveaux & matières" settings page (resources/views/academique/
 * niveaux-matieres.blade.php): populates the "Modifier" slide-panels from
 * the clicked row's data-edit-* attributes — same pattern as
 * parametres-dossiers.js's initTypeDocumentEdit().
 */
export function initAcademiqueSettings() {
    initNiveauEdit();
    initMatiereEdit();
}

function initNiveauEdit() {
    const form = document.getElementById('edit-niveau-form');
    if (!form) {
        return;
    }

    const libelleInput = document.getElementById('edit-niveau-libelle');
    const ordreInput = document.getElementById('edit-niveau-ordre');
    const cycleSelect = document.getElementById('edit-niveau-cycle');
    const premiereScolarisationCheckbox = document.getElementById('edit-niveau-premiere-scolarisation');
    const editUrlHidden = document.getElementById('edit-niveau-edit-url');

    document.querySelectorAll('[data-edit-niveau-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            libelleInput.value = trigger.dataset.editLibelle ?? '';
            ordreInput.value = trigger.dataset.editOrdre ?? '';
            cycleSelect.value = trigger.dataset.editCycle ?? 'primaire';
            premiereScolarisationCheckbox.checked = trigger.dataset.editPremiereScolarisation === '1';
        });
    });
}

function initMatiereEdit() {
    const form = document.getElementById('edit-matiere-form');
    if (!form) {
        return;
    }

    const nomInput = document.getElementById('edit-matiere-nom');
    const editUrlHidden = document.getElementById('edit-matiere-edit-url');

    document.querySelectorAll('[data-edit-matiere-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            nomInput.value = trigger.dataset.editNom ?? '';
        });
    });
}
