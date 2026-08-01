/**
 * Populates the "Modifier la fiche" <x-slide-panel> from the clicked row's
 * data-edit-* attributes, same pattern as account-edit.js. Champs
 * personnalisés values travel as a single JSON blob (data-edit-champs)
 * since the set of fields is dynamic.
 */
export function initEleveEdit() {
    const form = document.getElementById('edit-eleve-form');
    if (!form) {
        return;
    }

    const fields = {
        nom: document.getElementById('edit-eleve-nom'),
        prenom: document.getElementById('edit-eleve-prenom'),
        sexe: document.getElementById('edit-eleve-sexe'),
        date_naissance: document.getElementById('edit-eleve-date-naissance'),
        niveau_souhaite: document.getElementById('edit-eleve-niveau-souhaite'),
    };
    const editUrlHidden = document.getElementById('edit-eleve-edit-url');

    document.querySelectorAll('[data-edit-eleve-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            // Mirrored into a hidden field so `old('_edit_url')` can restore
            // the correct action if a validation error redirects back here.
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            fields.nom.value = trigger.dataset.editNom ?? '';
            fields.prenom.value = trigger.dataset.editPrenom ?? '';
            fields.sexe.value = trigger.dataset.editSexe ?? 'M';
            fields.date_naissance.value = trigger.dataset.editDateNaissance ?? '';
            fields.niveau_souhaite.value = trigger.dataset.editNiveauSouhaiteId ?? '';

            let champs = {};
            try {
                champs = JSON.parse(trigger.dataset.editChamps || '{}');
            } catch (error) {
                champs = {};
            }

            Object.entries(champs).forEach(([champId, valeur]) => {
                const input = document.getElementById(`edit-champ-${champId}`);
                if (input) {
                    input.value = valeur ?? '';
                }
            });
        });
    });
}
