/**
 * "Liste des tuteurs" page (resources/views/tuteurs/index.blade.php): the
 * "Modifier" pencil prefills the shared edit-tuteur-info panel, and "voir
 * les enfants" fetches GET tuteurs/{tuteur}/enfants as JSON to render into
 * the <x-fiche-modal id="tuteur-enfants"> singleton.
 *
 * Both triggers are delegated on `document` (rather than bound directly to
 * each row's button at init time) because the list's search box is now live
 * (live-search.js replaces #tuteurs-table-region's innerHTML on every
 * keystroke) — a one-time querySelectorAll().forEach(addEventListener)
 * would silently stop working for rows rendered after the first search,
 * same class of bug already fixed for the élèves list's action-menu /
 * fiche-trigger / edit-eleve-trigger. Since slide-panel.js's generic
 * `[data-panel-open]` handling is *also* init-time-only, both panels are
 * shown explicitly here too.
 */
export function initTuteurList() {
    const editForm = document.getElementById('edit-tuteur-info-form');
    const editUrlHidden = document.getElementById('edit-tuteur-info-edit-url');
    const nomPrenomInput = document.getElementById('edit-tuteur-info-nom-prenom');
    const telephoneInput = document.getElementById('edit-tuteur-info-telephone');
    const emailInput = document.getElementById('edit-tuteur-info-email');

    document.addEventListener('click', (event) => {
        const editTrigger = event.target.closest('[data-edit-tuteur-info-trigger]');
        if (editTrigger && editForm) {
            editForm.action = editTrigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = editTrigger.dataset.editUrl;
            }
            if (nomPrenomInput) {
                nomPrenomInput.value = editTrigger.dataset.editNomPrenom ?? '';
            }
            if (telephoneInput) {
                telephoneInput.value = editTrigger.dataset.editTelephone ?? '';
            }
            if (emailInput) {
                emailInput.value = editTrigger.dataset.editEmail ?? '';
            }
            document.querySelector('[data-panel="edit-tuteur-info"]')?.classList.add('show');
            document.querySelector('[data-panel-overlay="edit-tuteur-info"]')?.classList.add('show');
            return;
        }

        const enfantsTrigger = event.target.closest('[data-enfants-trigger]');
        if (enfantsTrigger) {
            fetch(enfantsTrigger.dataset.enfantsUrl, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then(renderEnfants)
                .catch(() => {});
            document.querySelector('[data-panel="tuteur-enfants"]')?.classList.add('show');
            document.querySelector('[data-panel-overlay="tuteur-enfants"]')?.classList.add('show');
        }
    });
}

function renderEnfants(data) {
    const { tuteur, enfants } = data;

    setText('tuteur-enfants-nom', `${tuteur.prenom ?? ''} ${tuteur.nom ?? ''}`.trim());
    setText('tuteur-enfants-telephone', tuteur.telephone || '—');
    setText('tuteur-enfants-email', tuteur.email || '—');

    const list = document.getElementById('tuteur-enfants-list');
    if (!list) {
        return;
    }

    list.innerHTML = enfants.length
        ? enfants.map((enfant) => `
            <div class="fiche-parcours-item">
                <a href="${escapeHTML(enfant.liste_url)}"><b>${escapeHTML(enfant.nom_complet)}</b></a> — ${escapeHTML(enfant.lien_parente ?? '')}
                <br><span>Matricule : ${escapeHTML(enfant.matricule || 'non renseigné')}</span>
            </div>
        `).join('')
        : '<p class="table-empty-state">Aucun élève lié à ce tuteur.</p>';
}

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = text;
    }
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
