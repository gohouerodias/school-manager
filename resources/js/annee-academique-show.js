/**
 * Année académique setup page (resources/views/academique/annees/show.blade.php):
 * every bit of client-side cascading/filtering + pending-list/edit-panel
 * wiring for its three tabs (programme / classes / affectations).
 */
export function initAnneeAcademiqueShow() {
    initAffectationMatiereFilter();
    initNiveauMatierePendingList();
    initNiveauMatiereEdit();
    initClasseLettreFilter();
    initClasseEdit();
}

/**
 * "Affecter un enseignant" panel: the "Matière" select starts with every
 * matière listed, but only those actually in the chosen classe's programme
 * (see Classe::matieres(), the `classe_matiere` pivot — itself inherited
 * from the niveau's programme, see Academique\ClasseController) should be
 * pickable. Each classe <option> carries its matière ids in
 * `data-matiere-ids` (comma-separated), set server-side; this just shows/
 * hides + disables the matière <option>s to match whenever the classe
 * changes.
 */
function initAffectationMatiereFilter() {
    const classeSelect = document.getElementById('new-affectation-classe');
    const matiereSelect = document.getElementById('new-affectation-matiere');

    if (!classeSelect || !matiereSelect) {
        return;
    }

    const matiereOptions = Array.from(matiereSelect.options).filter((option) => option.value !== '');
    const placeholder = matiereSelect.options[0];

    function syncMatieres() {
        const selected = classeSelect.options[classeSelect.selectedIndex];
        const matiereIds = (selected?.dataset.matiereIds ?? '').split(',').filter(Boolean);

        if (!classeSelect.value) {
            placeholder.textContent = "— Sélectionnez d'abord une classe —";
            matiereOptions.forEach((option) => {
                option.hidden = true;
                option.disabled = true;
            });
            matiereSelect.value = '';
            return;
        }

        placeholder.textContent = matiereIds.length ? '— Sélectionner —' : 'Aucune matière au programme de cette classe';

        matiereOptions.forEach((option) => {
            const inProgramme = matiereIds.includes(option.value);
            option.hidden = !inProgramme;
            option.disabled = !inProgramme;
        });

        if (matiereSelect.value && !matiereIds.includes(matiereSelect.value)) {
            matiereSelect.value = '';
        }
    }

    classeSelect.addEventListener('change', syncMatieres);
    syncMatieres();
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
