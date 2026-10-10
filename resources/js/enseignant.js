/**
 * Espace enseignant — "Saisie des notes" (resources/views/enseignant/
 * saisie-notes.blade.php), adapted from files/tableau-bord-enseignant_1.html:
 * the table itself is server-rendered (real data), this only wires up
 * autosave (note cells, subject comments, monthly bulletin) via real fetch()
 * calls to Enseignant\EspaceEnseignantController, replacing the mockup's
 * in-memory fake data.
 *
 * Imported here (rather than added as its own Vite entry) so the maternelle
 * grid (resources/views/enseignant/saisie-domaines.blade.php) and the
 * "Bulletin annuel — Observations" tab (resources/views/enseignant/partials/
 * observations-annuelles.blade.php, included by both) ride the same bundle
 * as the rest of the espace enseignant — each no-ops on any page missing its
 * own guard element/table.
 */
import './saisie-domaines';
import './observations-annuelles';
import { initNotificationsMenu } from './notifications-menu';
import { initSidebar } from './sidebar';

/**
 * Onglets « Notes du mois » / « Bulletin annuel — Observations » des
 * feuilles de saisie (saisie-notes / saisie-domaines). L'onglet ouvert est
 * mémorisé par page : après un rechargement (changement de mois…), on
 * revient au même onglet.
 */
function initSheetTabs() {
    const boutons = document.querySelectorAll('[data-sheet-tab-btn]');
    if (!boutons.length) return;

    const cle = `onglet-feuille:${window.location.pathname}`;

    function ouvrir(onglet) {
        boutons.forEach((btn) => btn.classList.toggle('active', btn.dataset.sheetTabBtn === onglet));
        document.querySelectorAll('[data-sheet-tab-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.sheetTabPanel !== onglet;
        });
        try {
            sessionStorage.setItem(cle, onglet);
        } catch {
            // Stockage indisponible : sans conséquence.
        }
    }

    boutons.forEach((btn) => btn.addEventListener('click', () => ouvrir(btn.dataset.sheetTabBtn)));

    let memorise = null;
    try {
        memorise = sessionStorage.getItem(cle);
    } catch {
        memorise = null;
    }
    if (memorise && document.querySelector(`[data-sheet-tab-btn="${memorise}"]`)) {
        ouvrir(memorise);
    }
}

document.addEventListener('DOMContentLoaded', initSheetTabs);
// Menu en tiroir sur téléphone (même script que l'espace administrateur).
document.addEventListener('DOMContentLoaded', initSidebar);

// Cloche des notifications ("Notes incomplètes" — voir
// NotifierNotesIncompletesCommand), dont les enseignants sont les seuls
// destinataires : même composant <x-notifications-menu> que l'app admin.
document.addEventListener('DOMContentLoaded', initNotificationsMenu);

document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('espace-enseignant-data');
    if (!dataEl) {
        // "Mes classes" page, or a state (no examen/matières) with no sheet.
        return;
    }

    const config = JSON.parse(dataEl.textContent);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const studentsById = new Map(config.students.map((s) => [String(s.eleveId), s]));

    initNoteCells();
    initSearch();
    initCommentPanel();

    /**
     * Notes no longer autosave per cell: typing marks the case "dirty" (gold
     * dot) and reveals the "Enregistrer les modifications" bar; every dirty
     * cell — modified or newly added — is sent together in one request when
     * that button is clicked (see saveNotesBatch() in
     * Enseignant\EspaceEnseignantController). "Annuler" reverts every dirty
     * input back to its last saved value without any request.
     */
    function initNoteCells() {
        const dirtyCells = new Map();
        const saveBar = document.getElementById('saveBar');
        const saveBarCount = document.getElementById('saveBarCount');
        const saveBarBtn = document.getElementById('saveBarBtn');
        const saveBarCancel = document.getElementById('saveBarCancel');

        document.querySelectorAll('#sheetTable td.note-cell input').forEach((input) => {
            if (input.disabled) return;
            input.addEventListener('input', () => onNoteInput(input));
        });

        /**
         * Primaire (config.parCriteres) : une case = critère minimal (/18) +
         * critère de perfectionnement (/2), total /20 calculé en direct —
         * même calcul que Note::totalDesCriteres() côté serveur. Ailleurs :
         * une seule note /20.
         */
        function lireCellule(td) {
            const lire = (el, max) => {
                const raw = el?.value.trim() ?? '';
                return raw === '' ? null : Math.max(0, Math.min(max, Number(raw)));
            };
            const minEl = td.querySelector('[data-critere="minimal"]');
            if (!minEl) {
                const valeur = lire(td.querySelector('input'), 20);
                return { valeur, signature: String(valeur ?? '') };
            }
            const minimal = lire(minEl, config.baremes.minimal);
            const perfectionnement = lire(td.querySelector('[data-critere="perfectionnement"]'), config.baremes.perfectionnement);
            const valeur = (minimal === null && perfectionnement === null)
                ? null
                : Math.round(((minimal ?? 0) + (perfectionnement ?? 0)) * 100) / 100;
            return { minimal, perfectionnement, valeur, signature: `${minimal ?? ''}|${perfectionnement ?? ''}` };
        }

        function signatureEnregistree(student, matiereId) {
            if (!config.parCriteres) {
                return String(student?.notes?.[matiereId] ?? '');
            }
            const criteres = student?.criteres?.[matiereId] ?? {};
            return `${criteres.minimal ?? ''}|${criteres.perfectionnement ?? ''}`;
        }

        function afficherTotal(td, valeur) {
            const total = td.querySelector('[data-role="total"]');
            if (total) total.textContent = valeur === null ? '—' : String(valeur);
        }

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

        function onNoteInput(input) {
            const row = input.closest('tr');
            const td = input.closest('td');
            const eleveId = row.dataset.studentId;
            const matiereId = td.dataset.matiereId;
            const key = `${eleveId}:${matiereId}`;
            const student = studentsById.get(eleveId);
            const cellule = lireCellule(td);
            const estModifiee = cellule.signature !== signatureEnregistree(student, matiereId);

            afficherTotal(td, cellule.valeur);
            td.classList.toggle('dirty', estModifiee);

            if (estModifiee) {
                dirtyCells.set(key, { input, td, eleveId, matiereId });
            } else {
                dirtyCells.delete(key);
            }

            toggleSaveBar();
        }

        function toggleSaveBar() {
            const count = dirtyCells.size;
            saveBar.classList.toggle('show', count > 0);
            saveBarCount.textContent = count > 0
                ? `${count} note${count > 1 ? 's' : ''} non enregistrée${count > 1 ? 's' : ''}`
                : '';
        }

        function cancelDirtyCells() {
            dirtyCells.forEach(({ input, td, eleveId, matiereId }) => {
                const student = studentsById.get(eleveId);
                const original = student?.notes?.[matiereId];
                if (config.parCriteres) {
                    const criteres = student?.criteres?.[matiereId] ?? {};
                    td.querySelector('[data-critere="minimal"]').value = criteres.minimal ?? '';
                    td.querySelector('[data-critere="perfectionnement"]').value = criteres.perfectionnement ?? '';
                    afficherTotal(td, original ?? null);
                } else {
                    input.value = original ?? '';
                }
                td.classList.remove('dirty');
            });
            dirtyCells.clear();
            toggleSaveBar();
        }

        function saveDirtyCells() {
            if (dirtyCells.size === 0) return;

            const entries = Array.from(dirtyCells.values());
            const notes = entries.map(({ td, eleveId, matiereId }) => {
                const cellule = lireCellule(td);
                if (config.parCriteres) {
                    return {
                        eleve_id: eleveId,
                        matiere_id: matiereId,
                        critere_minimal: cellule.minimal,
                        critere_perfectionnement: cellule.perfectionnement,
                    };
                }
                const input = td.querySelector('input');
                if (cellule.valeur !== null) input.value = cellule.valeur;
                return { eleve_id: eleveId, matiere_id: matiereId, valeur: cellule.valeur };
            });

            saveBarBtn.disabled = true;

            postJSON(config.urls.notesBatch, { examen_id: config.examenId, notes }).then((res) => {
                if (!res.ok) {
                    showToast(res.data.message || "Une erreur est survenue.");
                    return;
                }

                entries.forEach(({ td, eleveId, matiereId }) => {
                    const cellule = lireCellule(td);
                    const valeur = cellule.valeur;
                    const row = td.closest('tr');

                    const student = studentsById.get(eleveId);
                    if (student) {
                        student.notes[matiereId] = valeur;
                        if (config.parCriteres) {
                            student.criteres = student.criteres ?? {};
                            student.criteres[matiereId] = { minimal: cellule.minimal, perfectionnement: cellule.perfectionnement };
                        }
                        updateMoyenne(row, student);
                    }
                    afficherTotal(td, valeur);

                    td.classList.remove('dirty', 'low', 'high');
                    if (valeur !== null) {
                        if (valeur < 10) td.classList.add('low');
                        else if (valeur >= 16) td.classList.add('high');
                    }
                });

                dirtyCells.clear();
                toggleSaveBar();
                showToast(`✓ ${entries.length} note${entries.length > 1 ? 's' : ''} enregistrée${entries.length > 1 ? 's' : ''}`);
            }).catch(() => showToast("Échec de l'enregistrement — vérifiez votre connexion.")).finally(() => {
                saveBarBtn.disabled = false;
            });
        }
    }

    /**
     * Once a student's bulletin mensuel is Validé (US C.2), their note cells
     * lock for every enseignant until a titulaire dévalide it (US C.3) —
     * server-side enforcement lives in EspaceEnseignantController, this is
     * just the matching visual state.
     */
    function applyRowLockState(eleveId) {
        const student = studentsById.get(String(eleveId));
        const row = document.querySelector(`#sheetTable tbody tr[data-student-id="${eleveId}"]`);
        if (!student || !row) return;

        const estValide = student.bulletin?.statut === 'valide';
        row.querySelectorAll('td.note-cell').forEach((td) => {
            const editable = td.dataset.editable !== '0';
            td.querySelectorAll('input').forEach((input) => {
                // config.saisieFermee : délai dépassé, ou enseignant non
                // titulaire en maternelle/primaire (consultation seule).
                input.disabled = estValide || !editable || config.saisieFermee;
                if (!editable) {
                    input.title = "Lecture seule — vous n'enseignez pas cette matière";
                } else if (estValide) {
                    input.title = 'Bulletin validé — dévalidez-le pour modifier les notes.';
                }
            });
        });
        row.classList.toggle('bulletin-valide', estValide);
    }

    /**
     * La moyenne n'est affichée (côté titulaire uniquement — voir le
     * @if($isTitulaire) du blade) qu'une fois toutes les matières du
     * programme notées, jamais sur un sous-ensemble partiel — même règle
     * que EspaceEnseignantController::validerBulletin() côté serveur.
     *
     * Pondérée par le coefficient de chaque matière (config.matieres[].coefficient),
     * exactement comme App\Models\Bulletin::calculerMoyenne() et le calcul
     * initial du blade (voir saisie-notes.blade.php) — pour que la mise à
     * jour instantanée à la saisie affiche la même valeur que celle qui
     * apparaîtra après rechargement, et que celle vue côté admin.
     */
    function updateMoyenne(row, student) {
        const cell = row.querySelector('[data-role="moyenne"]');
        if (!cell) return;

        const values = Object.values(student.notes).filter((v) => v !== null && v !== undefined);
        const complet = config.matieres.length > 0 && values.length === config.matieres.length;

        if (!complet) {
            cell.textContent = '—';
            cell.title = "En attente — toutes les matières n'ont pas encore été notées";
            return;
        }

        const totalCoefficients = config.matieres.reduce((sum, m) => sum + Number(m.coefficient), 0);
        const somme = config.matieres.reduce((sum, m) => sum + Number(student.notes[m.id] ?? 0) * Number(m.coefficient), 0);
        cell.textContent = totalCoefficients > 0 ? (somme / totalCoefficients).toFixed(2) : '0.00';
        cell.title = '';
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
        // « Valider et signer » enregistre d'abord ce qui est saisi dans le
        // panneau (commentaire, appréciation…), puis valide : sinon le serveur
        // ne voit que l'ancienne version et refuse la validation.
        document.getElementById('bulletinValiderBtn')?.addEventListener('click', () => saveComment(currentEleveId, { puisValider: true }));
        document.getElementById('bulletinDevaliderBtn')?.addEventListener('click', () => validerOuDevalider(currentEleveId, false));

        function openPanel(eleveId) {
            currentEleveId = eleveId;
            const student = studentsById.get(eleveId);
            if (!student) return;

            document.getElementById('commentStudentName').textContent = `${student.nom.toUpperCase()} ${student.prenom}`;
            // Le panneau couvre la grille : on rappelle ici la moyenne et,
            // à côté de chaque matière, la note de l'apprenant, pour pouvoir
            // rédiger le commentaire sans refermer le panneau.
            document.getElementById('commentPeriodLabel').textContent = libelleMoyenne(student);

            const estValide = student.bulletin?.statut === 'valide';

            const wrap = document.getElementById('subjectCommentsWrap');
            wrap.innerHTML = config.matieres.map((m) => {
                const editable = config.matiereIdsEditables.includes(m.id);
                const locked = estValide || !editable || config.saisieFermee;
                const lockHint = !editable ? " — lecture seule, vous n'enseignez pas cette matière" : '';
                return `
                <div class="subject-comment-field">
                    <label class="subject-comment-label">
                        <span>Commentaire — ${escapeHTML(m.nom)}${lockHint}</span>
                        <span class="note-rappel ${classeNote(student.notes?.[m.id])}">${escapeHTML(libelleNote(student, m.id))}</span>
                    </label>
                    <textarea data-matiere-id="${m.id}" ${locked ? 'readonly' : ''} placeholder="Votre observation pour ${escapeHTML(m.nom)} ce mois-ci...">${escapeHTML(student.subjectComments?.[m.id] ?? '')}</textarea>
                </div>
            `;
            }).join('');

            const isTitulaire = config.isTitulaire;
            // Délai dépassé (config.saisieFermee) : tout le mois en lecture seule,
            // comme côté serveur (EspaceEnseignantController::reponsePeriodeFermee()).
            const bulletinLocked = !isTitulaire || estValide || config.saisieFermee;
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
                bulletinQualites: 'qualites',
                bulletinDefautsMajeurs: 'defautsMajeurs',
                bulletinDecisionPedagogique: 'decisionPedagogique',
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
            const complet = notesCompletes(student);
            validerBtn.style.display = (isTitulaire && !estValide && !config.saisieFermee) ? 'inline-flex' : 'none';
            validerBtn.disabled = !complet;
            validerBtn.title = complet ? '' : "Toutes les matières n'ont pas encore été notées pour cet apprenant — validation impossible.";
            document.getElementById('bulletinDevaliderBtn').style.display = (isTitulaire && estValide && !config.saisieFermee) ? 'inline-flex' : 'none';
            document.getElementById('commentPanelSave').style.display = (estValide || config.saisieFermee) ? 'none' : 'inline-flex';

            panel.classList.add('show');
            overlay.classList.add('show');
        }

        /** « 15 + 2 = 17 /20 » au primaire (critères), « 13,5 /20 » sinon. */
        function libelleNote(student, matiereId) {
            const note = student.notes?.[matiereId];
            if (note === null || note === undefined) return 'Non notée';
            const fr = (v) => String(v).replace('.', ',');
            const criteres = student.criteres?.[matiereId];
            if (config.parCriteres && criteres && (criteres.minimal !== null || criteres.perfectionnement !== null)) {
                return `${fr(criteres.minimal ?? 0)} + ${fr(criteres.perfectionnement ?? 0)} = ${fr(note)} /20`;
            }
            return `${fr(note)} /20`;
        }

        function classeNote(note) {
            if (note === null || note === undefined) return 'vide';
            if (note < 10) return 'basse';
            return note >= 16 ? 'haute' : '';
        }

        /** Même calcul pondéré que la colonne « Moyenne » (voir updateMoyenne()). */
        function libelleMoyenne(student) {
            if (!notesCompletes(student)) return "Moyenne : en attente (toutes les matières ne sont pas encore notées)";
            const totalCoefficients = config.matieres.reduce((sum, m) => sum + Number(m.coefficient), 0);
            const somme = config.matieres.reduce((sum, m) => sum + Number(student.notes[m.id] ?? 0) * Number(m.coefficient), 0);
            const moyenne = totalCoefficients > 0 ? somme / totalCoefficients : 0;
            return `Moyenne du mois : ${moyenne.toFixed(2).replace('.', ',')} /20`;
        }

        /**
         * Same rule as EspaceEnseignantController::notesCompletesPour() —
         * only meaningful for the titulaire, whose `student.notes` already
         * carries every matière of the programme (see EspaceEnseignantController::show()).
         */
        function notesCompletes(student) {
            const valeurs = Object.values(student.notes).filter((v) => v !== null && v !== undefined);

            return config.matieres.length > 0 && valeurs.length === config.matieres.length;
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

        function saveComment(eleveId, { puisValider = false } = {}) {
            if (!eleveId) return;
            const student = studentsById.get(eleveId);
            if (!student) return;

            const requests = [];

            document.querySelectorAll('#subjectCommentsWrap textarea').forEach((textarea) => {
                const matiereId = textarea.dataset.matiereId;
                const commentaire = textarea.value.trim();
                if ((student.subjectComments?.[matiereId] ?? '') === commentaire) return;

                requests.push(
                    postJSON(config.urls.commentaireMatiere, {
                        eleve_id: eleveId,
                        matiere_id: matiereId,
                        examen_id: config.examenId,
                        commentaire,
                    }).then((res) => {
                        if (res.ok) {
                            student.subjectComments = student.subjectComments || {};
                            if (commentaire) student.subjectComments[matiereId] = commentaire;
                            else delete student.subjectComments[matiereId];
                        }
                        return res;
                    })
                );
            });

            if (config.isTitulaire) {
                const selected = document.querySelector('.rating-option.selected');
                const text = document.getElementById('commentText').value.trim();
                const assiduite = document.getElementById('bulletinAssiduite').value.trim();
                const conduite = document.getElementById('bulletinConduite').value.trim();
                const qualites = document.getElementById('bulletinQualites').value.trim();
                const defautsMajeurs = document.getElementById('bulletinDefautsMajeurs').value.trim();
                const decisionPedagogique = document.getElementById('bulletinDecisionPedagogique').value.trim();
                requests.push(
                    postJSON(config.urls.bulletin, {
                        eleve_id: eleveId,
                        examen_id: config.examenId,
                        resultat_global: selected ? selected.dataset.rating : null,
                        appreciation: text || null,
                        assiduite: assiduite || null,
                        conduite: conduite || null,
                        qualites: qualites || null,
                        defauts_majeurs: defautsMajeurs || null,
                        decision_pedagogique: decisionPedagogique || null,
                    }).then((res) => {
                        if (res.ok) {
                            student.bulletin = {
                                resultat: selected ? selected.dataset.rating : null,
                                appreciation: text || null,
                                assiduite: assiduite || null,
                                conduite: conduite || null,
                                qualites: qualites || null,
                                defautsMajeurs: defautsMajeurs || null,
                                decisionPedagogique: decisionPedagogique || null,
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
                const hasContent = Object.keys(student.subjectComments || {}).length > 0
                    || !!student.bulletin?.appreciation || !!student.bulletin?.resultat;
                btn?.classList.toggle('filled', hasContent);

                if (puisValider) {
                    validerOuDevalider(eleveId, true);
                    return;
                }

                closePanel();
                showToast('✓ Commentaire(s) enregistré(s)');
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
