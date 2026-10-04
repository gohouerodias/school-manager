import { initTuteurQuickSearch } from './tuteur-quick-search';
import { askConfirmation } from './confirm-modal';
import { acceptAttribute, enMo, erreurFichierDocument, parseFormats } from './document-file-check';

/**
 * Fiche élève wizard (resources/views/eleves/wizard.blade.php): 4 étapes
 * (classe désirée, infos perso, parents/tuteurs, documents) rendered on one
 * page and submitted together. This module only handles pure client-side
 * concerns — step navigation, showing/hiding the transfer documents based on
 * the classe désirée picked in étape 1, and the "add a tuteur to the pending
 * list" mini-workflow (mirroring resources/js/account-invites.js), which
 * reuses the same "does this parent already exist" quick-search
 * (resources/js/tuteur-quick-search.js) as the fiche modal's own "Ajouter un
 * tuteur" / "Modifier le tuteur" panels.
 */
const TOTAL_STEPS = 4;

export function initEleveWizard() {
    const form = document.getElementById('eleve-wizard-form');

    if (!form) {
        return;
    }

    initStepNavigation();
    initDocumentConditionality();
    initWizardDropzones(form);
    initTuteurPendingList(form);
    initTotalUploadCheck(form);
    initDraftButton(form);
    initUnsavedChangesGuard(form);
}

/**
 * Prévient la perte des saisies : tant que le formulaire a été modifié sans
 * être enregistré, quitter la page (« Retour à la liste », menu, fil
 * d'Ariane…) demande d'abord confirmation via la fenêtre de confirmation
 * de l'app ; fermer l'onglet / recharger déclenche l'alerte du navigateur.
 * Écoute en phase de capture, donc avant page-loader.js : un départ annulé
 * n'affiche pas l'écran de chargement.
 */
function initUnsavedChangesGuard(form) {
    let modifie = false;
    let envoiEnCours = false;

    const marquerModifie = () => {
        modifie = true;
    };
    form.addEventListener('input', marquerModifie);
    form.addEventListener('change', marquerModifie);
    form.addEventListener('drop', marquerModifie);
    form.addEventListener('submit', (event) => {
        // Un envoi bloqué par une vérification (ex. taille totale) garde
        // la protection active.
        setTimeout(() => {
            envoiEnCours = !event.defaultPrevented;
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (modifie && !envoiEnCours) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    document.addEventListener('click', (event) => {
        if (!modifie || envoiEnCours || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        const lien = event.target.closest('a[href]');
        const href = lien?.getAttribute('href') ?? '';
        if (!lien || form.contains(lien) || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || lien.target === '_blank' || lien.hasAttribute('download')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        askConfirmation({
            title: 'Modifications non enregistrées',
            message: "Vous avez modifié cette fiche sans l'enregistrer. Si vous quittez maintenant, vos modifications seront perdues.",
            confirmLabel: 'Quitter sans enregistrer',
            danger: true,
            onConfirm: () => {
                modifie = false;
                window.location.href = lien.href;
            },
        });
    }, true);
}

const CHAMPS_IDENTITE_REQUIS = ['niveau_souhaite_id', 'nom', 'prenom', 'sexe', 'date_naissance'];

/**
 * « Sauvegarder en brouillon » n'apparaît que tant que la fiche est
 * incomplète (champ obligatoire vide ou document obligatoire manquant) —
 * une fiche complète se termine normalement avec « Terminer ».
 */
function initDraftButton(form) {
    const draftBtn = document.getElementById('wizard-draft-btn');
    if (!draftBtn) {
        return;
    }

    const estVide = (element) => !element || String(element.value ?? '').trim() === '';

    function ficheComplete() {
        const identiteIncomplete = CHAMPS_IDENTITE_REQUIS.some((nom) => estVide(form.elements.namedItem(nom)));
        if (identiteIncomplete) {
            return false;
        }

        const champIncomplet = [...form.querySelectorAll('[data-wizard-requis]')]
            .some((field) => estVide(field.querySelector('input, select, textarea')));
        if (champIncomplet) {
            return false;
        }

        return ![...form.querySelectorAll('.wizard-document-field')].some((field) => {
            const requis = field.dataset.obligatoire === '1'
                || (field.dataset.requisSiTransfert === '1' && field.style.display !== 'none');
            const input = field.querySelector('input[type="file"]');

            return requis && field.dataset.dejaFourni !== '1' && !(input?.files?.length > 0);
        });
    }

    function update() {
        draftBtn.style.display = ficheComplete() ? 'none' : 'inline-flex';
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('drop', () => setTimeout(update));
    update();
}

/**
 * Refuse l'envoi quand l'ensemble des documents dépasse la limite du
 * serveur (post_max_size, voir App\Support\LimitesEnvoi) : sinon le
 * navigateur téléverse tout pendant de longues minutes pour finir sur une
 * erreur « 413 Content Too Large » et une fiche non enregistrée.
 * Enregistré avant le loader de page (page-loader.js écoute sur document),
 * donc un envoi bloqué ici n'affiche pas l'écran de chargement.
 */
function initTotalUploadCheck(form) {
    const limite = Number(form.dataset.tailleMaxEnvoi || 0);
    if (!limite) {
        return;
    }

    form.addEventListener('submit', (event) => {
        const total = [...form.querySelectorAll('input[type="file"]')]
            .flatMap((input) => [...input.files])
            .reduce((somme, fichier) => somme + fichier.size, 0);

        // Marge pour les autres champs du formulaire et l'encodage multipart.
        if (total <= limite * 0.95) {
            return;
        }

        event.preventDefault();
        const message = `Les documents choisis pèsent ${enMo(total)} au total, au-delà de la limite du serveur (${enMo(limite)} par envoi). `
            + "Retirez ou réduisez certains documents, puis ajoutez-les ensuite un par un depuis la fiche de l'apprenant.";
        afficherErreurEnvoi(form, message);
    });
}

function afficherErreurEnvoi(form, message) {
    let alerte = document.getElementById('wizard-envoi-error');
    if (!alerte) {
        alerte = document.createElement('div');
        alerte.id = 'wizard-envoi-error';
        alerte.className = 'alert-error';
        form.prepend(alerte);
    }
    alerte.textContent = message;
    alerte.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function initStepNavigation() {
    const stepButtons = document.querySelectorAll('[data-wizard-step-btn]');
    const stepContents = document.querySelectorAll('[data-wizard-step]');
    const infoContents = document.querySelectorAll('[data-wizard-info]');
    const prevBtn = document.getElementById('wizard-prev-btn');
    const nextBtn = document.getElementById('wizard-next-btn');
    const finishBtn = document.getElementById('wizard-finish-btn');

    if (!stepButtons.length || !prevBtn || !nextBtn || !finishBtn) {
        return;
    }

    function currentStep() {
        const activeBtn = document.querySelector('[data-wizard-step-btn].active');
        return activeBtn ? Number(activeBtn.dataset.wizardStepBtn) : 1;
    }

    function goToStep(step) {
        step = Math.min(Math.max(step, 1), TOTAL_STEPS);

        stepButtons.forEach((btn) => {
            btn.classList.toggle('active', Number(btn.dataset.wizardStepBtn) === step);
        });
        stepContents.forEach((content) => {
            content.style.display = Number(content.dataset.wizardStep) === step ? 'block' : 'none';
        });
        infoContents.forEach((content) => {
            content.style.display = Number(content.dataset.wizardInfo) === step ? 'block' : 'none';
        });

        prevBtn.style.visibility = step === 1 ? 'hidden' : 'visible';
        nextBtn.style.display = step === TOTAL_STEPS ? 'none' : 'inline-flex';
        // Modifier une fiche existante : on peut enregistrer depuis
        // n'importe quelle étape (corriger un nom ne doit pas obliger à
        // parcourir les 4 étapes). Nouvelle fiche : « Terminer » à la fin.
        const enregistrableAChaqueEtape = document.getElementById('eleve-wizard-form')?.dataset.wizardMode === 'edit';
        finishBtn.style.display = step === TOTAL_STEPS || enregistrableAChaqueEtape ? 'inline-flex' : 'none';
    }

    stepButtons.forEach((btn) => {
        btn.addEventListener('click', () => goToStep(Number(btn.dataset.wizardStepBtn)));
    });
    prevBtn.addEventListener('click', () => goToStep(currentStep() - 1));
    nextBtn.addEventListener('click', () => goToStep(currentStep() + 1));

    // Server already sets the right step's markup visible (see wizard.blade.php's
    // $activeStep, computed from validation errors) — this just syncs the
    // nav buttons' visibility to match on first paint.
    goToStep(currentStep());
}

/**
 * Étape 4's "Bulletin de l'école précédente" / "Certificat de scolarité
 * antérieure" fields (TypeDocument::$requis_si_transfert) are hidden
 * whenever the classe désirée chosen in étape 1 is a niveau de première
 * scolarisation (Maternelle 1 / Maternelle 2 — see Niveau::$premiere_scolarisation).
 */
function initDocumentConditionality() {
    const select = document.getElementById('wizard-niveau-souhaite');

    if (!select) {
        return;
    }

    function update() {
        const option = select.options[select.selectedIndex];
        const premiereScolarisation = option?.dataset.premiereScolarisation === '1';

        document.querySelectorAll('.wizard-document-field[data-requis-si-transfert="1"]').forEach((field) => {
            field.style.display = premiereScolarisation ? 'none' : 'block';
        });
    }

    select.addEventListener('change', update);
    update();
}

/**
 * Étape 4's file fields: same click-to-browse + drag-and-drop dropzone as
 * "Ajouter un document" (see eleve-tuteur-document.js's initDropzone()), but
 * generalized to wire up several instances at once (one per type de
 * document) instead of a single hardcoded set of element ids — each
 * [data-wizard-dropzone] finds its own input/text/filename via querySelector
 * rather than a global id, so this scales to however many document fields
 * the "Paramètres des dossiers" config defines.
 */
function initWizardDropzones(form) {
    const tailleMaxFichier = Number(form.dataset.tailleMaxFichier || 0) || undefined;

    document.querySelectorAll('[data-wizard-dropzone]').forEach((dropzone) => {
        const fileInput = dropzone.querySelector('input[type="file"]');
        const textBlock = dropzone.querySelector('.wizard-dropzone-text');
        const filenameLabel = dropzone.querySelector('.wizard-dropzone-filename');
        const field = dropzone.closest('.wizard-document-field');
        const clientError = field?.querySelector('[data-wizard-dropzone-error]');
        const formats = parseFormats(fileInput?.dataset.formats);

        if (!fileInput || !filenameLabel) {
            return;
        }

        if (formats.length > 0) {
            fileInput.accept = acceptAttribute(formats);
        }

        function showClientError(message) {
            if (clientError) {
                clientError.textContent = message;
                clientError.style.display = message ? 'block' : 'none';
            }
            field?.classList.toggle('invalid', Boolean(message));
        }

        function showSelectedFile(file) {
            if (!file) {
                resetDropzone();
                return;
            }

            // Refusé tout de suite (format/taille) : on vide le champ plutôt
            // que de laisser croire que le fichier est accepté jusqu'à
            // l'enregistrement final.
            const erreur = erreurFichierDocument(file, formats, tailleMaxFichier);
            if (erreur) {
                fileInput.value = '';
                resetDropzone();
                showClientError(erreur);
                return;
            }

            showClientError('');

            const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
            filenameLabel.textContent = `📎 ${file.name} (${sizeMb} Mo)`;
            filenameLabel.style.display = 'block';
            textBlock.style.display = 'none';
            dropzone.classList.add('has-file');
        }

        function resetDropzone() {
            filenameLabel.style.display = 'none';
            filenameLabel.textContent = '';
            textBlock.style.display = 'block';
            dropzone.classList.remove('has-file');
        }

        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', () => showSelectedFile(fileInput.files[0]));

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
            }
        });
    });
}

function initTuteurPendingList(form) {
    const nomPrenomInput = document.getElementById('wizard-tuteur-nom-prenom');
    const lienSelect = document.getElementById('wizard-tuteur-lien');
    const telephoneInput = document.getElementById('wizard-tuteur-telephone');
    const emailInput = document.getElementById('wizard-tuteur-email');
    const addBtn = document.getElementById('wizard-tuteur-add-btn');
    const pendingSection = document.getElementById('wizard-tuteur-pending-section');
    const pendingList = document.getElementById('wizard-tuteur-pending-list');
    const pendingCount = document.getElementById('wizard-tuteur-pending-count');

    if (!nomPrenomInput || !addBtn || !pendingList) {
        return;
    }

    // Same reusable quick-search as the fiche modal's "Ajouter un tuteur" /
    // "Modifier le tuteur" panels (see resources/js/tuteur-quick-search.js) —
    // its fields (#wizard-tuteur-nom-prenom, -telephone, -email, -suggestions,
    // -match) already follow the "{prefix}-*" naming it expects.
    const quickSearch = initTuteurQuickSearch('wizard-tuteur', {
        rechercheUrl: form.dataset.tuteurRechercheUrl,
    });

    // Rehydrates the pending list from old('tuteurs') (see wizard.blade.php's
    // data-old-tuteurs) so a failed "Terminer" — for a reason unrelated to
    // étape 3, e.g. a missing document — doesn't silently wipe out tuteurs
    // the agent had already added to the list before submitting.
    let pending = parseOldTuteurs(form.dataset.oldTuteurs);

    function renderPending() {
        pendingCount.textContent = String(pending.length);
        pendingSection.style.display = pending.length ? 'block' : 'none';

        pendingList.innerHTML = pending
            .map(
                (item, index) => `
                <div class="pending-item">
                    <div>
                        <span class="pmail">${escapeHTML(item.nomPrenom)}${item.existingId ? ' (déjà enregistré)' : ''}</span><br>
                        <span class="prole">${escapeHTML(item.lien)}${item.telephone ? ' · ' + escapeHTML(item.telephone) : ''}</span>
                    </div>
                    <button type="button" class="premove" data-remove-index="${index}">✕</button>
                </div>`
            )
            .join('');

        pendingList.querySelectorAll('[data-remove-index]').forEach((btn) => {
            btn.addEventListener('click', () => {
                pending.splice(Number(btn.dataset.removeIndex), 1);
                renderPending();
            });
        });
    }

    renderPending();

    addBtn.addEventListener('click', () => {
        const nomPrenom = nomPrenomInput.value.trim();

        if (!nomPrenom) {
            nomPrenomInput.style.borderColor = 'var(--red)';
            return;
        }
        nomPrenomInput.style.borderColor = '';

        pending.push({
            existingId: quickSearch.getMatch()?.id ?? null,
            nomPrenom,
            lien: lienSelect.value,
            telephone: telephoneInput.value.trim(),
            email: emailInput.value.trim(),
        });
        renderPending();

        nomPrenomInput.value = '';
        telephoneInput.value = '';
        emailInput.value = '';
        quickSearch.reset();
    });

    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-generated-tuteur]').forEach((el) => el.remove());

        pending.forEach((item, index) => {
            const fields = {
                existing_id: item.existingId ?? '',
                nom_prenom: item.nomPrenom,
                lien_parente: item.lien,
                telephone: item.telephone,
                email: item.email,
            };

            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `tuteurs[${index}][${key}]`;
                input.value = value;
                input.dataset.generatedTuteur = 'true';
                form.appendChild(input);
            });
        });
    });
}

/**
 * Parses the `data-old-tuteurs` JSON (see wizard.blade.php) back into the
 * shape initTuteurPendingList()'s `pending` array expects. Tolerant of
 * missing/malformed data (no old input yet, or a genuinely empty tuteurs
 * array) — always falls back to an empty list rather than throwing.
 */
function parseOldTuteurs(raw) {
    if (!raw) {
        return [];
    }

    let parsed;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return [];
    }

    if (!Array.isArray(parsed)) {
        return [];
    }

    return parsed
        .filter((item) => item && String(item.nom_prenom ?? '').trim() !== '')
        .map((item) => ({
            existingId: item.existing_id || null,
            nomPrenom: String(item.nom_prenom ?? ''),
            lien: item.lien_parente || 'Père',
            telephone: String(item.telephone ?? ''),
            email: String(item.email ?? ''),
        }));
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
