import { refreshDropdownSelect } from './dropdown-select';
import { initTuteurQuickSearch } from './tuteur-quick-search';

/**
 * "Ajouter un tuteur" / "Ajouter un document" panels, opened from within the
 * fiche apprenant modal (eleve-fiche.js). Both are shared singleton panels
 * (like edit-eleve), so their form.action is set right before opening, from
 * the URL templates carried on the fiche panel's dataset (see
 * eleves/index.blade.php: data-tuteur-url-template / data-document-url-template)
 * combined with the currently open élève's id (fichePanel.dataset.currentEleveId,
 * set by eleve-fiche.js).
 */
export function initEleveTuteurDocument() {
    const fichePanel = document.querySelector('[data-panel="fiche"]');
    const tuteurForm = document.getElementById('add-tuteur-form');
    const documentForm = document.getElementById('add-document-form');

    // Same reusable "does this parent already exist" quick-search used by
    // the fiche élève wizard's étape 3 (see resources/js/tuteur-quick-search.js)
    // — wiring it into a new form is just naming its fields "{prefix}-*" and
    // calling this with that prefix, no extra JS needed.
    const addTuteurQuickSearch = initTuteurQuickSearch('tuteur', {
        rechercheUrl: fichePanel?.dataset.tuteurRechercheUrl,
    });

    if (fichePanel && tuteurForm) {
        const tuteurActionHidden = document.getElementById('add-tuteur-action');

        document.querySelectorAll('[data-panel-open="add-tuteur"]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const eleveId = fichePanel.dataset.currentEleveId;
                const template = fichePanel.dataset.tuteurUrlTemplate;
                if (eleveId && template) {
                    const action = template.replace('__ID__', eleveId);
                    tuteurForm.action = action;
                    // Mirrored into a hidden field so `old('_action')` can
                    // restore the correct action if a validation error
                    // redirects back here.
                    if (tuteurActionHidden) {
                        tuteurActionHidden.value = action;
                    }
                }
                tuteurForm.reset();
                // form.reset() doesn't fire `change` on individual fields,
                // so the "Lien de parenté" custom dropdown's trigger label
                // would otherwise still show whatever was picked last time.
                refreshDropdownSelect(tuteurForm.querySelector('select'));
                addTuteurQuickSearch.reset();
            });
        });
    }

    initEditTuteurPanel();

    if (fichePanel && documentForm) {
        const documentActionHidden = document.getElementById('add-document-action');

        document.querySelectorAll('[data-panel-open="add-document"]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const eleveId = fichePanel.dataset.currentEleveId;
                const template = fichePanel.dataset.documentUrlTemplate;
                if (eleveId && template) {
                    const action = template.replace('__ID__', eleveId);
                    documentForm.action = action;
                    if (documentActionHidden) {
                        documentActionHidden.value = action;
                    }
                }
                documentForm.reset();
                refreshDropdownSelect(documentForm.querySelector('select'));
                resetDropzone();
                updateFormatsHint();
                hideFileClientError();
            });
        });

        documentForm.addEventListener('submit', (event) => {
            const fileInput = document.getElementById('document-file-input');
            if (fileInput && fileInput.files.length === 0) {
                event.preventDefault();
                showFileClientError();
            }
        });
    }

    const typeSelect = document.getElementById('document-type');
    if (typeSelect) {
        typeSelect.addEventListener('change', updateFormatsHint);
    }

    initDropzone();
}

/**
 * "Modifier le tuteur" panel, opened from the pencil button on a tuteur
 * card (built dynamically in eleve-fiche.js's renderFiche(), so it doesn't
 * exist yet when this module's other init-time listeners are attached).
 * Delegated on #fiche-parents-list, which is present in the static markup
 * and simply gets its innerHTML replaced on every fiche render.
 */
function initEditTuteurPanel() {
    const parentsList = document.getElementById('fiche-parents-list');
    const form = document.getElementById('edit-tuteur-form');
    if (!parentsList || !form) {
        return;
    }

    const editUrlHidden = document.getElementById('edit-tuteur-edit-url');
    const nomPrenomInput = document.getElementById('edit-tuteur-nom-prenom');
    const lienSelect = document.getElementById('edit-tuteur-lien');
    const telephoneInput = document.getElementById('edit-tuteur-telephone');
    const emailInput = document.getElementById('edit-tuteur-email');

    const editTuteurQuickSearch = initTuteurQuickSearch('edit-tuteur', {
        rechercheUrl: document.querySelector('[data-panel="fiche"]')?.dataset.tuteurRechercheUrl,
    });

    parentsList.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-edit-tuteur-trigger]');
        if (!trigger) {
            return;
        }

        form.action = trigger.dataset.editUrl;
        // Mirrored into a hidden field so `old('_edit_url')` can restore the
        // correct action if a validation error redirects back here.
        if (editUrlHidden) {
            editUrlHidden.value = trigger.dataset.editUrl;
        }
        if (nomPrenomInput) {
            nomPrenomInput.value = trigger.dataset.editNomPrenom ?? '';
        }
        if (lienSelect) {
            lienSelect.value = trigger.dataset.editLien ?? '';
            refreshDropdownSelect(lienSelect);
        }
        if (telephoneInput) {
            telephoneInput.value = trigger.dataset.editTelephone ?? '';
        }
        if (emailInput) {
            emailInput.value = trigger.dataset.editEmail ?? '';
        }

        // Clear any suggestion/match left over from a previous "Modifier"
        // opened during this same fiche session.
        editTuteurQuickSearch.reset();

        document.querySelector('[data-panel="edit-tuteur"]')?.classList.add('show');
        document.querySelector('[data-panel-overlay="edit-tuteur"]')?.classList.add('show');
    });
}

function showFileClientError() {
    const error = document.getElementById('document-file-client-error');
    if (error) {
        error.style.display = 'block';
    }
}

function hideFileClientError() {
    const error = document.getElementById('document-file-client-error');
    if (error) {
        error.style.display = 'none';
    }
}

function updateFormatsHint() {
    const typeSelect = document.getElementById('document-type');
    const hint = document.getElementById('document-formats-hint');
    if (!typeSelect || !hint) {
        return;
    }

    const formats = typeSelect.selectedOptions[0]?.dataset.formats;
    hint.textContent = formats ? `${formats.split(',').join(', ')} — 5 Mo maximum` : 'PDF, JPG ou PNG — 5 Mo maximum';
}

/**
 * Makes the dropzone actually work: click-to-browse (via the hidden file
 * input) and real drag-and-drop, both feeding the same <input type="file">
 * so the surrounding <form> submits it normally.
 */
function initDropzone() {
    const dropzone = document.getElementById('document-dropzone');
    const fileInput = document.getElementById('document-file-input');

    if (!dropzone || !fileInput) {
        return;
    }

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    fileInput.addEventListener('change', () => {
        showSelectedFile(fileInput.files[0]);
        if (fileInput.files.length > 0) {
            hideFileClientError();
        }
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'dragend'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove('dragover');

        const file = event.dataTransfer?.files?.[0];
        if (file) {
            fileInput.files = event.dataTransfer.files;
            showSelectedFile(file);
            hideFileClientError();
        }
    });
}

function showSelectedFile(file) {
    const textBlock = document.getElementById('document-dropzone-text');
    const filenameLabel = document.getElementById('document-filename');
    if (!filenameLabel) {
        return;
    }

    if (!file) {
        resetDropzone();
        return;
    }

    const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
    filenameLabel.textContent = `📎 ${file.name} (${sizeMb} Mo)`;
    filenameLabel.style.display = 'block';
    if (textBlock) {
        textBlock.style.display = 'none';
    }
}

function resetDropzone() {
    const fileInput = document.getElementById('document-file-input');
    const textBlock = document.getElementById('document-dropzone-text');
    const filenameLabel = document.getElementById('document-filename');

    if (fileInput) {
        fileInput.value = '';
    }
    if (filenameLabel) {
        filenameLabel.style.display = 'none';
        filenameLabel.textContent = '';
    }
    if (textBlock) {
        textBlock.style.display = 'block';
    }
}
