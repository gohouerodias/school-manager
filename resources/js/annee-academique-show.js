/**
 * Année académique setup page (resources/views/academique/annees/show.blade.php):
 * every bit of client-side cascading/filtering + pending-list/edit-panel
 * wiring for its three tabs (programme / classes / affectations).
 */
export function initAnneeAcademiqueShow() {
    initGererAffectationPanel();
    initNiveauMatierePendingList();
    initNiveauMatiereEdit();
    initClasseLettreFilter();
    initClasseEdit();
}

/**
 * "Gérer" l'affectation d'une classe — a single shared panel (see
 * resources/views/academique/annees/show.blade.php's "Affectation des
 * enseignants" table, adapted from files/gestion-comptes_1.html), repopulated
 * from the clicked row's `data-*` attributes each time it opens: the
 * enseignants already affected (each removable in one action, all their
 * matières at once — see Academique\AffectationEnseignantController::
 * destroyEnseignant()), a form to affect a new enseignant to one or several
 * matières at once (US A.3), and — Collège only — who's titulaire, restricted
 * to enseignants already affected to this classe (US A.4). Maternelle/
 * Primaire classes hide the matière checkboxes and titulaire section
 * entirely: one teacher owns the whole classe (see StoreAffectationEnseignant
 * Request::classeEstEnModeEntiere()).
 */
function initGererAffectationPanel() {
    const list = document.getElementById('gerer-affectation-list');

    if (!list) {
        return;
    }

    const matieresCheckWrap = document.getElementById('gerer-affectation-matieres-check');
    const matieresField = document.getElementById('gerer-affectation-matieres-field');
    const classeEntiereHint = document.getElementById('gerer-affectation-classe-entiere-hint');
    const classeIdInput = document.getElementById('gerer-affectation-classe-id');
    const titulaireForm = document.getElementById('gerer-affectation-titulaire-form');
    const titulaireSelect = document.getElementById('gerer-affectation-titulaire-select');
    const titulaireSection = document.getElementById('gerer-affectation-titulaire-section');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const matieresMap = JSON.parse(document.getElementById('gerer-affectation-matieres-map')?.textContent || '{}');
    const oldMatiereIds = JSON.parse(document.getElementById('gerer-affectation-old-matiere-ids')?.textContent || '[]');

    function populate(trigger) {
        const classeId = trigger.dataset.classeId;
        const classeNom = trigger.dataset.classeNom ?? '';
        const estClasseEntiere = trigger.dataset.classeEntiere === '1';
        const matiereIds = (trigger.dataset.matiereIds ?? '').split(',').filter(Boolean);
        const enseignants = JSON.parse(trigger.dataset.enseignants || '[]');

        document.querySelector('[data-panel="gerer-affectation"] .panel-head h2').textContent = `Affectation — ${classeNom}`;
        classeIdInput.value = classeId;

        list.innerHTML = enseignants.length
            ? enseignants.map((e) => `
                <div class="pending-item" style="background:var(--paper);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="avatar avatar-ens">${initiales(e.nom)}</div>
                        <div>
                            <span class="pmail">${escapeHTML(e.nom)}${e.estTitulaire ? ' · Titulaire' : ''}</span><br>
                            <span class="prole">${escapeHTML(e.matieres)}</span>
                        </div>
                    </div>
                    <form method="POST" action="${e.destroyUrl}" data-confirm-submit data-confirm-danger="1" data-confirm-label="Retirer" data-confirm-title="Retirer cet enseignant" data-confirm-message="Retirer ${escapeHTML(e.nom)} de « ${escapeHTML(classeNom)} » ?" style="display:inline;">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="premove" title="Retirer de la classe">✕</button>
                    </form>
                </div>`).join('')
            : '<p class="hint">Aucun enseignant affecté pour l’instant.</p>';

        if (estClasseEntiere) {
            if (matieresField) matieresField.style.display = 'none';
            if (classeEntiereHint) classeEntiereHint.style.display = 'block';
            titulaireSection.style.display = 'none';
            return;
        }

        if (matieresField) matieresField.style.display = 'block';
        if (classeEntiereHint) classeEntiereHint.style.display = 'none';

        matieresCheckWrap.innerHTML = matiereIds.map((id) => `
            <label>
                <input type="checkbox" name="matiere_ids[]" value="${id}" ${oldMatiereIds.includes(String(id)) ? 'checked' : ''}>
                ${escapeHTML(matieresMap[id] ?? '')}
            </label>`).join('');

        titulaireSection.style.display = enseignants.length ? 'block' : 'none';
        titulaireForm.action = trigger.dataset.titulaireUrl;
        titulaireSelect.innerHTML = enseignants.map((e) => `<option value="${e.id}" ${e.estTitulaire ? 'selected' : ''}>${escapeHTML(e.nom)}</option>`).join('');
    }

    document.querySelectorAll('[data-gerer-affectation-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => populate(trigger));
    });

    // A validation error on the "add" sub-form redirects back with the panel
    // reopened (see the "reopen-panel" meta tag, panel-error-reopen.js) but
    // empty — re-populate it for the classe that was actually being edited.
    const reopenPanelId = document.querySelector('meta[name="reopen-panel"]')?.content;
    if (reopenPanelId === 'gerer-affectation' && classeIdInput.value) {
        const trigger = document.querySelector(`[data-gerer-affectation-trigger][data-classe-id="${classeIdInput.value}"]`);
        if (trigger) {
            populate(trigger);
        }
    }
}

/**
 * "Ajouter des matières au programme" panel: builds up a list of
 * matière + coefficient pairs client-side (same "pending list" idea as the
 * fiche élève wizard's étape 3 tuteurs, see initTuteurPendingList() in
 * eleve-wizard.js) before submitting them all in one POST as
 * `matieres[i][matiere_id]` / `matieres[i][coefficient]` — so adding a
 * niveau's whole programme doesn't mean reopening this panel once per
 * matière.
 */
function initNiveauMatierePendingList() {
    const form = document.getElementById('new-niveau-matiere-form');
    const matiereSelect = document.getElementById('new-niveau-matiere-matiere');
    const coefficientInput = document.getElementById('new-niveau-matiere-coefficient');
    const addBtn = document.getElementById('new-niveau-matiere-add-btn');
    const pendingSection = document.getElementById('new-niveau-matiere-pending-section');
    const pendingList = document.getElementById('new-niveau-matiere-pending-list');
    const pendingCount = document.getElementById('new-niveau-matiere-pending-count');

    if (!form || !matiereSelect || !addBtn || !pendingList) {
        return;
    }

    let pending = [];

    function renderPending() {
        pendingCount.textContent = String(pending.length);
        pendingSection.style.display = pending.length ? 'block' : 'none';

        pendingList.innerHTML = pending
            .map(
                (item, index) => `
                <div class="pending-item">
                    <div>
                        <span class="pmail">${escapeHTML(item.nom)}</span><br>
                        <span class="prole">Coefficient ${escapeHTML(item.coefficient)}</span>
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

    function resetPending() {
        pending = [];
        renderPending();
    }

    document.querySelectorAll('[data-panel-open="new-niveau-matiere"]').forEach((trigger) => {
        trigger.addEventListener('click', resetPending);
    });

    addBtn.addEventListener('click', () => {
        const matiereId = matiereSelect.value;
        const option = matiereSelect.options[matiereSelect.selectedIndex];
        const nom = option?.dataset.nom ?? option?.textContent ?? '';
        const coefficient = coefficientInput.value || '1';

        if (!matiereId) {
            matiereSelect.style.borderColor = 'var(--red)';
            return;
        }
        if (pending.some((item) => item.matiereId === matiereId)) {
            matiereSelect.style.borderColor = 'var(--red)';
            return;
        }
        matiereSelect.style.borderColor = '';

        pending.push({ matiereId, nom, coefficient });
        renderPending();

        matiereSelect.value = '';
        coefficientInput.value = '1';
    });

    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-generated-niveau-matiere]').forEach((el) => el.remove());

        pending.forEach((item, index) => {
            const fields = { matiere_id: item.matiereId, coefficient: item.coefficient };

            Object.entries(fields).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `matieres[${index}][${key}]`;
                input.value = value;
                input.dataset.generatedNiveauMatiere = 'true';
                form.appendChild(input);
            });
        });
    });

    renderPending();
}

/**
 * "Modifier le coefficient" panel, opened from a matière chip's ✎ button
 * (see resources/views/academique/annees/show.blade.php) — same
 * data-edit-*-trigger pattern as academique-settings.js's initNiveauEdit().
 */
function initNiveauMatiereEdit() {
    const form = document.getElementById('edit-niveau-matiere-form');
    if (!form) {
        return;
    }

    const label = document.getElementById('edit-niveau-matiere-label');
    const coefficientInput = document.getElementById('edit-niveau-matiere-coefficient');
    const editUrlHidden = document.getElementById('edit-niveau-matiere-edit-url');

    document.querySelectorAll('[data-edit-niveau-matiere-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            label.textContent = `${trigger.dataset.editMatiereNom ?? ''} — ${trigger.dataset.editNiveauLibelle ?? ''}`;
            coefficientInput.value = trigger.dataset.editCoefficient ?? '';
        });
    });
}

/**
 * "Ajouter une classe" panel: the "Lettre" select only offers letters not
 * already used by another classe of the chosen niveau, this année (see
 * `data-lettres-utilisees` on each niveau <option>, set server-side from
 * $lettresParNiveau) — same cascading-filter idea as
 * initAffectationMatiereFilter() above.
 */
function initClasseLettreFilter() {
    const niveauSelect = document.getElementById('new-classe-niveau');
    const lettreSelect = document.getElementById('new-classe-lettre');

    if (!niveauSelect || !lettreSelect) {
        return;
    }

    const lettreOptions = Array.from(lettreSelect.options).filter((option) => option.value !== '');
    const placeholder = lettreSelect.options[0];

    function syncLettres() {
        const selected = niveauSelect.options[niveauSelect.selectedIndex];
        const utilisees = (selected?.dataset.lettresUtilisees ?? '').split(',').filter(Boolean);

        if (!niveauSelect.value) {
            placeholder.textContent = "— Sélectionnez d'abord un niveau —";
            lettreOptions.forEach((option) => {
                option.hidden = true;
                option.disabled = true;
            });
            lettreSelect.value = '';
            return;
        }

        placeholder.textContent = '— Sélectionner —';

        lettreOptions.forEach((option) => {
            const dejaUtilisee = utilisees.includes(option.value);
            option.hidden = dejaUtilisee;
            option.disabled = dejaUtilisee;
        });

        if (lettreSelect.value && utilisees.includes(lettreSelect.value)) {
            lettreSelect.value = '';
        }
    }

    niveauSelect.addEventListener('change', syncLettres);
    syncLettres();
}

/**
 * "Modifier la classe" panel: only the lettre is editable (see
 * ClasseController::update()) — its select excludes letters already used by
 * *other* classes of the same niveau (this classe's own current letter
 * stays pickable, preselected).
 */
function initClasseEdit() {
    const form = document.getElementById('edit-classe-form');
    if (!form) {
        return;
    }

    const niveauLibelle = document.getElementById('edit-classe-niveau-libelle');
    const lettreSelect = document.getElementById('edit-classe-lettre');
    const editUrlHidden = document.getElementById('edit-classe-edit-url');
    const lettreOptions = Array.from(lettreSelect.options);

    document.querySelectorAll('[data-edit-classe-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            niveauLibelle.textContent = trigger.dataset.editNiveauLibelle ?? '—';

            const utilisees = (trigger.dataset.editLettresUtilisees ?? '').split(',').filter(Boolean);
            lettreOptions.forEach((option) => {
                const dejaUtilisee = utilisees.includes(option.value);
                option.hidden = dejaUtilisee;
                option.disabled = dejaUtilisee;
            });

            lettreSelect.value = trigger.dataset.editLettre ?? '';
        });
    });
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

/**
 * Same two-letter initials rule as the <x-avatar> Blade component, for the
 * enseignant avatars rendered client-side in the "Gérer" affectation panel
 * (see initGererAffectationPanel()).
 */
function initiales(nom) {
    return (nom ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
}
