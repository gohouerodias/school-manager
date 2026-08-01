/**
 * "Paramètres des dossiers" > "Ajouter un champ" panel: shows the Options
 * textarea only when the selected type is "Liste déroulante".
 */
export function initParametresDossiers() {
    const typeSelect = document.querySelector('[data-champ-type-select]');
    const optionsField = document.getElementById('new-champ-options-field');

    if (typeSelect && optionsField) {
        const syncVisibility = () => {
            optionsField.style.display = typeSelect.value === 'liste_deroulante' ? 'block' : 'none';
        };

        typeSelect.addEventListener('change', syncVisibility);
        syncVisibility();
    }

    initTypeDocumentEdit();
}

/**
 * Populates the "Modifier le type de document" <x-slide-panel> from the
 * clicked row's data-edit-* attributes, same pattern as eleve-edit.js /
 * account-edit.js. The "obligatoire" toggle's checkbox has no `name` — only
 * the hidden field next to it is submitted — so the value sent to the
 * server is fully controlled here rather than relying on browsers'
 * duplicate-same-name-field resolution order.
 */
function initTypeDocumentEdit() {
    const form = document.getElementById('edit-type-document-form');
    if (!form) {
        return;
    }

    const libelleInput = document.getElementById('edit-type-libelle');
    const formatInputs = {
        PDF: document.getElementById('edit-type-format-pdf'),
        JPG: document.getElementById('edit-type-format-jpg'),
        PNG: document.getElementById('edit-type-format-png'),
    };
    const obligatoireCheckbox = document.getElementById('edit-type-obligatoire');
    const obligatoireHidden = document.getElementById('edit-type-obligatoire-hidden');
    const editUrlHidden = document.getElementById('edit-type-document-edit-url');

    const syncObligatoire = () => {
        obligatoireHidden.value = obligatoireCheckbox.checked ? '1' : '0';
    };

    obligatoireCheckbox?.addEventListener('change', syncObligatoire);

    document.querySelectorAll('[data-edit-type-document-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            // Mirrored into a hidden field so `old('_edit_url')` can restore
            // the correct action if a validation error redirects back here.
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            libelleInput.value = trigger.dataset.editLibelle ?? '';

            const formats = (trigger.dataset.editFormats ?? '').split(',').filter(Boolean);
            Object.entries(formatInputs).forEach(([format, input]) => {
                if (input) {
                    input.checked = formats.includes(format);
                }
            });

            obligatoireCheckbox.checked = trigger.dataset.editObligatoire === '1';
            syncObligatoire();
        });
    });
}
