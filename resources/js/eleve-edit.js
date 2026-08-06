import { refreshDropdownSelect } from './dropdown-select';

/**
 * Populates the "Modifier la fiche" <x-slide-panel> from the clicked row's
 * data-edit-* attributes, same pattern as account-edit.js. Champs
 * personnalisés values travel as a single JSON blob (data-edit-champs)
 * since the set of fields is dynamic.
 *
 * The trigger click is delegated on `document` (rather than bound directly
 * to each `[data-edit-eleve-trigger]` at init time) so rows swapped in
 * later by the élèves list's live search (live-search.js) keep working.
 * Since slide-panel.js's generic `[data-panel-open]` handling is *also*
 * init-time-only, the panel is opened explicitly here too.
 *
 * Setting `.value` on a select.role-select doesn't fire `change`, so its
 * custom dropdown-select.js trigger label would go stale without the
 * explicit refreshDropdownSelect() calls below.
 *
 * NOTE (fiche élève wizard): the "Modifier la fiche" slide-panel this module
 * used to populate has been removed from resources/views/eleves/index.blade.php
 * — its only trigger (the fiche modal's "Modifier" button) now links
 * straight to the wizard's "modifier" page instead (see eleve-fiche.js's
 * populateEditEleveTrigger()). This file is no longer imported from app.js
 * and is kept only pending the user's explicit confirmation to delete it.
 */
export function initEleveEdit() {
    const form = document.getElementById('edit-eleve-form');
    if (!form) {
        return;
    }

    const fields = {
        matricule: document.getElementById('edit-eleve-matricule'),
        nom: document.getElementById('edit-eleve-nom'),
        prenom: document.getElementById('edit-eleve-prenom'),
        sexe: document.getElementById('edit-eleve-sexe'),
        date_naissance: document.getElementById('edit-eleve-date-naissance'),
        niveau_souhaite: document.getElementById('edit-eleve-niveau-souhaite'),
    };
    const editUrlHidden = document.getElementById('edit-eleve-edit-url');

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-edit-eleve-trigger]');
        if (!trigger) {
            return;
        }

        form.action = trigger.dataset.editUrl;
        // Mirrored into a hidden field so `old('_edit_url')` can restore
        // the correct action if a validation error redirects back here.
        if (editUrlHidden) {
            editUrlHidden.value = trigger.dataset.editUrl;
        }
        if (fields.matricule) {
            fields.matricule.value = trigger.dataset.editMatricule ?? '';
        }
        fields.nom.value = trigger.dataset.editNom ?? '';
        fields.prenom.value = trigger.dataset.editPrenom ?? '';
        fields.sexe.value = trigger.dataset.editSexe ?? 'M';
        fields.date_naissance.value = trigger.dataset.editDateNaissance ?? '';
        fields.niveau_souhaite.value = trigger.dataset.editNiveauSouhaiteId ?? '';
        refreshDropdownSelect(fields.sexe);
        refreshDropdownSelect(fields.niveau_souhaite);

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
                if (input.tagName === 'SELECT') {
                    refreshDropdownSelect(input);
                }
            }
        });

        document.querySelector('[data-panel="edit-eleve"]')?.classList.add('show');
        document.querySelector('[data-panel-overlay="edit-eleve"]')?.classList.add('show');
    });
}
