/**
 * Espace enseignant — "Grille d'évaluation" de la maternelle
 * (resources/views/enseignant/saisie-domaines.blade.php) : même principe
 * que resources/js/enseignant.js (saisie des matières du primaire), mais
 * chaque cellule est une valeur qualitative (TS/S/PS, voir
 * App\Enums\NiveauQualitatif) plutôt qu'une note chiffrée, avec une
 * observation par domaine facultative saisie depuis le panneau
 * d'appréciation plutôt qu'en ligne.
 */
document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('espace-maternelle-data');
    if (!dataEl) {
        return;
    }

    const config = JSON.parse(dataEl.textContent);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const studentsById = new Map(config.students.map((s) => [String(s.eleveId), s]));

    /** Libellé complet affiché en tooltip sur chaque pastille TS/S/PS — voir
     * resources/views/enseignant/saisie-domaines.blade.php, qui pose le même
     * title au premier rendu ; ce mapping ne sert qu'à le reconstruire côté
     * JS (ex. après verrouillage, voir applyRowLockState()). */
    const LIBELLES_QUALITATIFS = { ts: 'Très satisfaisant', s: 'Satisfaisant', ps: 'Peu satisfaisant' };

    /**
     * Une fois le bulletin mensuel d'un apprenant Validé, ses pastilles
     * TS/S/PS se verrouillent (pour le titulaire comme pour tout autre
     * enseignant) jusqu'à ce que le titulaire dévalide — même règle que
     * enseignant.js's applyRowLockState() pour les notes chiffrées du
     * primaire/collège ; l'application côté serveur vit dans
     * EspaceEnseignantController. Sans cet appel (au chargement, puis après
     * chaque validation/dévalidation), les pastilles restaient cliquables
     * après signature du bulletin. Déclarée avant les appels ci-dessous : les
     * `const`/fonctions plus bas ne sont pas encore initialisées tant que
     * l'exécution n'a pas atteint leur ligne (contrairement aux déclarations
     * `function` hoistées), et initDomaineCells() l'appelle immédiatement.
     */
    function applyRowLockState(eleveId) {
        const student = studentsById.get(String(eleveId));
        const row = document.querySelector(`#sheetTable tbody tr[data-student-id="${eleveId}"]`);
        if (!student || !row) return;

        const estValide = student.bulletin?.statut === 'valide';
        row.querySelectorAll('td.domaine-cell .qual-pill').forEach((pill) => {
            pill.disabled = estValide;
            const libelle = LIBELLES_QUALITATIFS[pill.dataset.valeur] ?? '';
            pill.title = estValide
                ? `${libelle} — Bulletin validé, dévalidez-le pour modifier les évaluations.`
                : libelle;
        });
        row.classList.toggle('bulletin-valide', estValide);
    }

    initDomaineCells();
    initSearch();
    initCommentPanel();

    /**
     * Cliquer un pastille TS/S/PS marque la case "dirty" (comme les notes du
     * primaire) et révèle la barre « Enregistrer les modifications » ; toutes
     * les cases modifiées sont envoyées ensemble en une requête (voir
     * saveDomainesBatch() côté serveur). L'observation de chaque domaine se
     * gère séparément, depuis le panneau (voir initCommentPanel()).
     */
    function initDomaineCells() {
        const dirtyCells = new Map();
        const saveBar = document.getElementById('saveBar');
        const saveBarCount = document.getElementById('saveBarCount');
        const saveBarBtn = document.getElementById('saveBarBtn');
        const saveBarCancel = document.getElementById('saveBarCancel');

        document.querySelectorAll('#sheetTable td.domaine-cell .qual-pill').forEach((pill) => {
            if (pill.disabled) return;
            pill.addEventListener('click', () => onPillClick(pill));
        });

        document.querySelectorAll('#sheetTable tbody tr').forEach((row) => {
            applyRowLockState(row.dataset.studentId);
        });

        saveBarBtn?.addEventListener('click', saveDirtyCells);
        saveBarCancel?.addEventListener('click', cancelDirtyCells);

        window.addEventListener('beforeunload', (event) => {
            if (dirtyCells.size === 0) return;
            event.preventDefault();
            event.returnValue = '';
        });

        function onPillClick(pill) {
            const td = pill.closest('td');
            const row = pill.closest('tr');
            const eleveId = row.dataset.studentId;
            const domaineId = td.dataset.domaineId;
            const key = `${eleveId}:${domaineId}`;
            const student = studentsById.get(eleveId);
            const original = student?.valeurs?.[domaineId] ?? null;

            const dejaSelectionnee = pill.classList.contains('selected');
            const nouvelleValeur = dejaSelectionnee ? null : pill.dataset.valeur;

            td.querySelectorAll('.qual-pill').forEach((p) => p.classList.remove('selected'));
            if (nouvelleValeur) {
                pill.classList.add('selected');
            }

            const estModifiee = String(nouvelleValeur ?? '') !== String(original ?? '');
            td.classList.toggle('dirty', estModifiee);

            if (estModifiee) {
                dirtyCells.set(key, { td, eleveId, domaineId, valeur: nouvelleValeur });
            } else {
                dirtyCells.delete(key);
            }

            toggleSaveBar();
        }

        function toggleSaveBar() {
            const count = dirtyCells.size;
            saveBar.classList.toggle('show', count > 0);
            saveBarCount.textContent = count > 0
                ? `${count} évaluation${count > 1 ? 's' : ''} non enregistrée${count > 1 ? 's' : ''}`
                : '';
        }

        function cancelDirtyCells() {
            dirtyCells.forEach(({ td, eleveId, domaineId }) => {
                const student = studentsById.get(eleveId);
                const original = student?.valeurs?.[domaineId] ?? null;
                td.querySelectorAll('.qual-pill').forEach((p) => p.classList.toggle('selected', p.dataset.valeur === original));
                td.classList.remove('dirty');
            });
            dirtyCells.clear();
            toggleSaveBar();
        }

        function saveDirtyCells() {
            if (dirtyCells.size === 0) return;

            const entries = Array.from(dirtyCells.values());
            const evaluations = entries.map(({ eleveId, domaineId, valeur }) => {
                const student = studentsById.get(eleveId);
                return {
                    eleve_id: eleveId,
                    domaine_evaluation_id: domaineId,
                    valeur,
                    observation: student?.observations?.[domaineId] ?? null,
                };
            });

            saveBarBtn.disabled = true;

            postJSON(config.urls.domainesBatch, { examen_id: config.examenId, evaluations }).then((res) => {
                if (!res.ok) {
                    showToast(res.data.message || "Une erreur est survenue.");
                    return;
                }

                entries.forEach(({ td, eleveId, domaineId, valeur }) => {
                    const student = studentsById.get(eleveId);
                    if (student) {
                        student.valeurs[domaineId] = valeur;
                    }
                    td.classList.remove('dirty');
                });

                dirtyCells.clear();
                toggleSaveBar();
                showToast(`✓ ${entries.length} évaluation${entries.length > 1 ? 's' : ''} enregistrée${entries.length > 1 ? 's' : ''}`);
            }).catch(() => showToast("Échec de l'enregistrement — vérifiez votre connexion.")).finally(() => {
                saveBarBtn.disabled = false;
            });
        }
    }

    function initSearch() {
        const input = document.getElementById('studentSearch');
        if (!input) return;

        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            document.querySelectorAll('#sheetTable tbody tr').forEach((tr) => {
                if (!tr.dataset.search) return;
                tr.style.display = (!q || tr.dataset.search.includes(q)) ? '' : 'none';
            });
        });
    }

    function initCommentPanel() {
        const panel = document.getElementById('commentPanel');
        const overlay = document.getElementById('overlay');
        if (!panel || !overlay) return;

        let currentEleveId = null;

        document.querySelectorAll('.comment-btn').forEach((btn) => {
            btn.addEventListener('click', () => openPanel(btn.dataset.studentId));
        });

        document.getElementById('commentPanelClose')?.addEventListener('click', closePanel);
        document.getElementById('commentPanelCancel')?.addEventListener('click', closePanel);
        overlay.addEventListener('click', closePanel);

        document.querySelectorAll('.rating-option').forEach((opt) => {
            opt.addEventListener('click', () => {
                if (document.getElementById('ratingOptions').classList.contains('locked')) return;
                document.querySelectorAll('.rating-option').forEach((o) => o.classList.remove('selected'));
                opt.classList.add('selected');
            });
        });

        document.getElementById('commentPanelSave')?.addEventListener('click', () => saveComment(currentEleveId));
        document.getElementById('bulletinValiderBtn')?.addEventListener('click', () => validerOuDevalider(currentEleveId, true));
        document.getElementById('bulletinDevaliderBtn')?.addEventListener('click', () => validerOuDevalider(currentEleveId, false));

        function openPanel(eleveId) {
            currentEleveId = eleveId;
            const student = studentsById.get(eleveId);
            if (!student) return;

            document.getElementById('commentStudentName').textContent = `${student.nom.toUpperCase()} ${student.prenom}`;
            document.getElementById('commentPeriodLabel').textContent = '';

            const estValide = student.bulletin?.statut === 'valide';

            const wrap = document.getElementById('subjectCommentsWrap');
            wrap.innerHTML = config.domaines.map((d) => {
                const locked = estValide;
                return `
                <div class="subject-comment-field">
                    <label>Observation — ${escapeHTML(d.nom)}</label>
                    <textarea data-domaine-id="${d.id}" ${locked ? 'readonly' : ''} placeholder="Facultatif — observation pour ${escapeHTML(d.nom)} ce mois-ci...">${escapeHTML(student.observations?.[d.id] ?? '')}</textarea>
                </div>
            `;
            }).join('');

            const isTitulaire = config.isTitulaire;
            const bulletinLocked = !isTitulaire || estValide;
            document.getElementById('titulaireLockNote').style.display = isTitulaire ? 'none' : 'flex';
            document.getElementById('titulaireLockName').textContent = config.titulaireNom ?? '';
            document.getElementById('bulletinValideLock').style.display = (isTitulaire && estValide) ? 'flex' : 'none';
            document.getElementById('ratingOptions').classList.toggle('locked', bulletinLocked);
            const commentText = document.getElementById('commentText');
            commentText.classList.toggle('locked', bulletinLocked);
            commentText.readOnly = bulletinLocked;
            commentText.value = student.bulletin?.appreciation ?? '';

            const bulletinTextFields = {
                bulletinAssiduite: 'assiduite',
                bulletinConduite: 'conduite',
            };
            Object.entries(bulletinTextFields).forEach(([inputId, key]) => {
                const input = document.getElementById(inputId);
                input.readOnly = bulletinLocked;
                input.value = student.bulletin?.[key] ?? '';
            });

            document.querySelectorAll('.rating-option').forEach((o) => {
                o.classList.toggle('selected', student.bulletin?.resultat === o.dataset.rating);
            });

            const statutBadge = document.getElementById('bulletinStatutBadge');
            const statutWrap = document.getElementById('bulletinStatutWrap');
            if (student.bulletin) {
                statutWrap.style.display = 'block';
                statutBadge.textContent = estValide
                    ? `Validé${student.bulletin.valideParNom ? ' par ' + student.bulletin.valideParNom : ''}`
                    : 'Brouillon';
                statutBadge.classList.toggle('chip-success', estValide);
            } else {
                statutWrap.style.display = 'none';
            }

            const validerBtn = document.getElementById('bulletinValiderBtn');
            const complet = domainesCompletes(student);
            validerBtn.style.display = (isTitulaire && !estValide) ? 'inline-flex' : 'none';
            validerBtn.disabled = !complet;
            validerBtn.title = complet ? '' : "Tous les domaines n'ont pas encore été évalués pour cet apprenant — validation impossible.";
            document.getElementById('bulletinDevaliderBtn').style.display = (isTitulaire && estValide) ? 'inline-flex' : 'none';
            document.getElementById('commentPanelSave').style.display = estValide ? 'none' : 'inline-flex';

            panel.classList.add('show');
            overlay.classList.add('show');
        }

        /**
         * Même règle que EspaceEnseignantController::notesCompletesPour()
         * (branche maternelle) : tous les domaines de la classe doivent avoir
         * une valeur pour cet apprenant.
         */
        function domainesCompletes(student) {
            const valeurs = Object.values(student.valeurs).filter((v) => v !== null && v !== undefined);

            return config.domaines.length > 0 && valeurs.length === config.domaines.length;
        }

        function closePanel() {
            panel.classList.remove('show');
            overlay.classList.remove('show');
            currentEleveId = null;
        }

        function validerOuDevalider(eleveId, valider) {
            if (!eleveId) return;
            const student = studentsById.get(eleveId);
            if (!student) return;

            const url = valider ? config.urls.bulletinValider : config.urls.bulletinDevalider;

            postJSON(url, { eleve_id: eleveId, examen_id: config.examenId }).then((res) => {
                if (!res.ok) {
                    showToast(res.data.message || "Une erreur est survenue.");
                    return;
                }

                student.bulletin = student.bulletin || {};
                student.bulletin.statut = res.data.statut;
                applyRowLockState(eleveId);
                openPanel(eleveId);
                showToast(valider ? '✓ Bulletin validé et signé' : '✓ Bulletin dévalidé');
            }).catch(() => showToast("Échec de l'enregistrement — vérifiez votre connexion."));
        }

        function saveComment(eleveId) {
            if (!eleveId) return;
            const student = studentsById.get(eleveId);
            if (!student) return;

            const requests = [];
            const evaluations = [];

            document.querySelectorAll('#subjectCommentsWrap textarea').forEach((textarea) => {
                const domaineId = textarea.dataset.domaineId;
                const observation = textarea.value.trim();
                if ((student.observations?.[domaineId] ?? '') === observation) return;

                evaluations.push({
                    eleve_id: eleveId,
                    domaine_evaluation_id: domaineId,
                    valeur: student.valeurs?.[domaineId] ?? null,
                    observation: observation || null,
                });
            });

            if (evaluations.length > 0) {
                requests.push(
                    postJSON(config.urls.domainesBatch, { examen_id: config.examenId, evaluations }).then((res) => {
                        if (res.ok) {
                            evaluations.forEach(({ domaine_evaluation_id, observation }) => {
                                student.observations = student.observations || {};
                                if (observation) student.observations[domaine_evaluation_id] = observation;
                                else delete student.observations[domaine_evaluation_id];
                            });
                        }
                        return res;
                    })
                );
            }

            if (config.isTitulaire) {
                const selected = document.querySelector('.rating-option.selected');
                const text = document.getElementById('commentText').value.trim();
                const assiduite = document.getElementById('bulletinAssiduite').value.trim();
                const conduite = document.getElementById('bulletinConduite').value.trim();
                requests.push(
                    postJSON(config.urls.bulletin, {
                        eleve_id: eleveId,
                        examen_id: config.examenId,
                        resultat_global: selected ? selected.dataset.rating : null,
                        appreciation: text || null,
                        assiduite: assiduite || null,
                        conduite: conduite || null,
                        qualites: null,
                        defauts_majeurs: null,
                        decision_pedagogique: null,
                    }).then((res) => {
                        if (res.ok) {
                            student.bulletin = {
                                resultat: selected ? selected.dataset.rating : null,
                                appreciation: text || null,
                                assiduite: assiduite || null,
                                conduite: conduite || null,
                                statut: 'brouillon',
                                valideParNom: null,
                            };
                            applyRowLockState(eleveId);
                        }
                        return res;
                    })
                );
            }

            Promise.all(requests).then((results) => {
                const failed = results.find((r) => !r.ok);
                if (failed) {
                    showToast(failed.data?.message || "Une erreur est survenue.");
                    return;
                }

                const btn = document.querySelector(`.comment-btn[data-student-id="${eleveId}"]`);
                const hasContent = Object.keys(student.observations || {}).length > 0
                    || !!student.bulletin?.appreciation || !!student.bulletin?.resultat;
                btn?.classList.toggle('filled', hasContent);

                closePanel();
                showToast('✓ Appréciation(s) enregistrée(s)');
            }).catch(() => showToast("Échec de l'enregistrement — vérifiez votre connexion."));
        }
    }

    function postJSON(url, body) {
        return fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        }).then(async (response) => ({
            ok: response.ok,
            status: response.status,
            data: await response.json().catch(() => ({})),
        }));
    }

    function showToast(msg) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2200);
    }

    function escapeHTML(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }
});
