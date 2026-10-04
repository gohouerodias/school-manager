import { refreshDropdownSelect } from './dropdown-select';
import { initTuteurQuickSearch } from './tuteur-quick-search';
import { acceptAttribute, enMo, erreurFichierDocument, parseFormats, TAILLE_MAX_DOCUMENT_OCTETS } from './document-file-check';

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
    initEditStatutParcoursPanel();

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
                // Ce bouton (onglet Documents général) n'est jamais scopé à
                // une inscription — form.reset() a déjà remis le champ caché
                // à vide, mais on masque aussi l'indice affiché par
                // initAddDocumentFromFrisePanel() s'il traînait d'une
                // précédente ouverture depuis la frise.
                hideDocumentInscriptionHint();
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

    initAddDocumentFromFrisePanel();

    const typeSelect = document.getElementById('document-type');
    if (typeSelect) {
        typeSelect.addEventListener('change', updateFormatsHint);
    }

    initDropzone();
}

/**
 * "+ Document justificatif" button on a frise item (see eleve-fiche.js's
 * friseDocumentsHTML(), currently only rendered for statut "Transféré
 * entrant"). Opens the same shared "add-document" panel as the Documents
 * tab's general button, but pre-fills the hidden `inscription_id` field so
 * the upload is attached to this specific année rather than to the élève in
 * general (see DocumentController::store()). Delegated on
 * #fiche-parcours-list, since the frise is rebuilt on every fiche render —
 * this button doesn't exist yet when the module's other init-time listeners
 * are attached (same reasoning as initEditStatutParcoursPanel() above).
 */
function initAddDocumentFromFrisePanel() {
    const parcoursList = document.getElementById('fiche-parcours-list');
    const documentForm = document.getElementById('add-document-form');
    if (!parcoursList || !documentForm) {
        return;
    }

    const documentActionHidden = document.getElementById('add-document-action');
    const inscriptionIdHidden = document.getElementById('add-document-inscription-id');

    parcoursList.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-add-document-frise-trigger]');
        if (!trigger) {
            return;
        }

        documentForm.action = trigger.dataset.uploadUrl;
        if (documentActionHidden) {
            documentActionHidden.value = trigger.dataset.uploadUrl;
        }

        documentForm.reset();
        refreshDropdownSelect(documentForm.querySelector('select'));
        resetDropzone();
        updateFormatsHint();
        hideFileClientError();

        // form.reset() ci-dessus a remis inscription_id à vide (sa valeur
        // HTML par défaut) — on la fixe *après*, pour cibler cette inscription.
        if (inscriptionIdHidden) {
            inscriptionIdHidden.value = trigger.dataset.inscriptionId ?? '';
        }
        showDocumentInscriptionHint(trigger.dataset.annee);

        document.querySelector('[data-panel="add-document"]')?.classList.add('show');
        document.querySelector('[data-panel-overlay="add-document"]')?.classList.add('show');
    });
}

function showDocumentInscriptionHint(annee) {
    const hint = document.getElementById('add-document-inscription-hint');
    if (!hint) {
        return;
    }
    hint.textContent = `Ce document sera rattaché à l'année ${annee ?? '—'} sur la frise du parcours scolaire.`;
    hint.style.display = 'block';
}

function hideDocumentInscriptionHint() {
    const hint = document.getElementById('add-document-inscription-hint');
    if (hint) {
        hint.style.display = 'none';
    }
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

/**
 * "Modifier le statut" panel, opened from a pencil button on a frise item
 * (see eleve-fiche.js's editStatutTriggerHTML()). Unlike the tuteur/document
 * panels above, no URL template is needed: each frise item already carries
 * its own ready-to-use `data-edit-url` (the backend returns one
 * `statut_update_url` per Inscription — see Eleves\EleveController::fiche()).
 */
function initEditStatutParcoursPanel() {
    const parcoursList = document.getElementById('fiche-parcours-list');
    const form = document.getElementById('edit-statut-parcours-form');
    if (!parcoursList || !form) {
        return;
    }

    const editUrlHidden = document.getElementById('edit-statut-parcours-edit-url');
    const anneeLabel = document.getElementById('edit-statut-parcours-annee');
    const statutSelect = document.getElementById('edit-statut-parcours-statut');

    parcoursList.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-edit-statut-trigger]');
        if (!trigger) {
            return;
        }

        form.action = trigger.dataset.editUrl;
        if (editUrlHidden) {
            editUrlHidden.value = trigger.dataset.editUrl;
        }
        if (anneeLabel) {
            anneeLabel.textContent = trigger.dataset.editAnnee ?? '—';
        }
        if (statutSelect) {
            statutSelect.value = trigger.dataset.editStatut ?? 'normal';
            refreshDropdownSelect(statutSelect);
        }

        document.querySelector('[data-panel="edit-statut-parcours"]')?.classList.add('show');
        document.querySelector('[data-panel-overlay="edit-statut-parcours"]')?.classList.add('show');
    });
}

function showFileClientError(message = "Sélectionnez un fichier avant d'enregistrer.") {
    const error = document.getElementById('document-file-client-error');
    if (error) {
        error.textContent = message;
        error.style.display = 'block';
    }
}

/** Limite réelle du serveur (App\Support\LimitesEnvoi), 5 Mo par défaut. */
function tailleMaxFichier() {
    return Number(document.getElementById('document-dropzone')?.dataset.tailleMaxFichier || 0) || TAILLE_MAX_DOCUMENT_OCTETS;
}

function selectedTypeFormats() {
    return parseFormats(document.getElementById('document-type')?.selectedOptions[0]?.dataset.formats);
}

/**
 * Vérifie tout de suite le fichier choisi (format du type sélectionné +
 * 5 Mo max, voir document-file-check.js) : un fichier refusé est retiré du
 * champ au lieu d'être affiché comme accepté jusqu'à l'enregistrement.
 */
function acceptOrRejectFile(file) {
    if (!file) {
        showSelectedFile(null);
        return;
    }

    const erreur = erreurFichierDocument(file, selectedTypeFormats(), tailleMaxFichier());
    if (erreur) {
        resetDropzone();
        showFileClientError(erreur);
        return;
    }

    showSelectedFile(file);
    hideFileClientError();
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
    const maximum = `${enMo(tailleMaxFichier())} maximum`;
    hint.textContent = formats ? `${formats.split(',').join(', ')} — ${maximum}` : `PDF, JPG ou PNG — ${maximum}`;

    const fileInput = document.getElementById('document-file-input');
    if (fileInput) {
        fileInput.accept = acceptAttribute(parseFormats(formats));

        // Le fichier déjà choisi doit aussi respecter les formats du
        // nouveau type sélectionné.
        if (fileInput.files.length > 0) {
            acceptOrRejectFile(fileInput.files[0]);
        }
    }
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

    fileInput.addEventListener('change', () => acceptOrRejectFile(fileInput.files[0]));

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
            acceptOrRejectFile(file);
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
