/**
 * "Paramètres des dossiers" > "Ajouter un champ" panel: the "Type" field is
 * a grid of clickable cards wrapping hidden radio inputs (same
 * hide-the-input-style-the-label pattern as .toggle-switch elsewhere in the
 * app), rather than a plain <select>. This shows the Options textarea only
 * when "Liste déroulante" is picked, and toggles a `.checked-card` class so
 * CSS can highlight/animate the selected card (kept in JS rather than
 * relying purely on a `:checked` CSS selector so it's simple to reset when
 * the panel is reopened for a fresh "Ajouter un champ").
 */
export function initParametresDossiers() {
    const typeRadios = document.querySelectorAll('[data-champ-type-select]');
    const optionsField = document.getElementById('new-champ-options-field');

    if (typeRadios.length) {
        const syncCards = () => {
            typeRadios.forEach((radio) => {
                radio.closest('.type-choice-card')?.classList.toggle('checked-card', radio.checked);
            });

            if (optionsField) {
                const selected = Array.from(typeRadios).find((radio) => radio.checked);
                optionsField.style.display = selected?.value === 'liste_deroulante' ? 'block' : 'none';
            }
        };

        typeRadios.forEach((radio) => radio.addEventListener('change', syncCards));
        syncCards();
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
