/**
 * "Consulter la fiche" modal (<x-fiche-modal id="fiche">): fetches
 * GET eleves/{eleve}/fiche as JSON on trigger click and renders the 4 tabs
 * (Identité, Parents/Tuteurs, Parcours scolaire, Documents).
 */
export function initEleveFiche() {
    // Delegated on `document` (rather than bound directly to each
    // `[data-fiche-trigger]` at init time) so rows swapped in later by the
    // élèves list's live search (live-search.js) keep working. Since
    // slide-panel.js's generic `[data-panel-open]` handling is *also*
    // init-time-only, the panel is opened explicitly here too.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-fiche-trigger]');
        if (!trigger) {
            return;
        }

        // "Ajouter un tuteur"/"Ajouter un document" (eleve-tuteur-document.js)
        // need to know which élève is currently open; the fiche URL is
        // .../eleves/{id}/fiche, so the id is lifted straight from it.
        const fichePanel = document.querySelector('[data-panel="fiche"]');
        const match = trigger.dataset.ficheUrl?.match(/\/eleves\/(\d+)\/fiche/);
        if (fichePanel && match) {
            fichePanel.dataset.currentEleveId = match[1];
        }

        fetch(trigger.dataset.ficheUrl, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then(renderFiche)
            .catch(() => {});

        document.querySelector('[data-panel="fiche"]')?.classList.add('show');
        document.querySelector('[data-panel-overlay="fiche"]')?.classList.add('show');
    });

    document.querySelectorAll('[data-fiche-tab]').forEach((tabBtn) => {
        tabBtn.addEventListener('click', () => {
            document.querySelectorAll('[data-fiche-tab]').forEach((btn) => btn.classList.remove('active'));
            document.querySelectorAll('[data-fiche-content]').forEach((content) => {
                content.style.display = 'none';
            });

            tabBtn.classList.add('active');
            const target = document.querySelector(`[data-fiche-content="${tabBtn.dataset.ficheTab}"]`);
            if (target) {
                target.style.display = 'block';
            }
        });
    });
}

function renderFiche(data) {
    const { identite, parents, parcours, documents } = data;

    setText('fiche-nom-famille', identite.nom);
    setText('fiche-prenom', identite.prenom);
    setText('fiche-matricule', identite.matricule);
    setText('fiche-classe', identite.classe || '—');
    setText('fiche-date-creation', identite.date_creation);
    setText('fiche-statut', identite.statut === 'archive' ? 'Archivé' : 'Actif');

    const avatarImg = document.getElementById('fiche-avatar-img');
    const avatarIcon = document.getElementById('fiche-avatar-icon');
    if (avatarImg && avatarIcon) {
        if (identite.photo_url) {
            avatarImg.src = identite.photo_url;
            avatarImg.style.display = 'block';
            avatarIcon.style.display = 'none';
        } else {
            avatarImg.removeAttribute('src');
            avatarImg.style.display = 'none';
            avatarIcon.style.display = 'block';
        }
    }

    const grid = document.getElementById('fiche-identite-grid');
    if (grid) {
        const fixedFields = [
            ['Sexe', identite.sexe === 'F' ? 'Féminin' : 'Masculin'],
            ['Date de naissance', identite.date_naissance],
        ];
        // "Classe désirée" (niveau souhaité) is only shown while the élève
        // has no fixed classe yet — once a real Inscription exists, the
        // niveau souhaité is no longer relevant.
        if (!identite.a_une_classe) {
            fixedFields.push(['Classe désirée', identite.niveau_souhaite || 'Non précisé']);
        }
        const allFields = fixedFields.concat((identite.champs || []).map((c) => [c.libelle, c.valeur || '—']));
        grid.innerHTML = allFields.map(([label, value]) => fieldHTML(label, value)).join('');
    }

    const eleveId = document.querySelector('[data-panel="fiche"]')?.dataset.currentEleveId;

    const parentsList = document.getElementById('fiche-parents-list');
    if (parentsList) {
        parentsList.innerHTML = parents.length
            ? parents.map((p) => `
                <div class="fiche-parent">
                    <div>
                        <b>${escapeHTML(p.nom)}</b> — ${escapeHTML(p.lien ?? '')}
                        <br><span>${escapeHTML(p.telephone ?? '—')}${p.email ? ' · ' + escapeHTML(p.email) : ''}</span>
                    </div>
                    <div class="fiche-parent-actions">
                        ${editTuteurTriggerHTML(eleveId, p)}
                        ${deleteFormHTML('tuteur-delete-url-template', eleveId, p.id, `Retirer ${p.nom} de la fiche ?`)}
                    </div>
                </div>
            `).join('')
            : '<p class="table-empty-state">Aucun parent/tuteur enregistré.</p>';
    }

    const parcoursList = document.getElementById('fiche-parcours-list');
    if (parcoursList) {
        parcoursList.innerHTML = parcours.length
            ? parcours.map((p) => `
                <div class="fiche-parcours-item">
                    <b>${escapeHTML(p.annee ?? '—')}</b> — ${escapeHTML(p.classe ?? 'Sans classe')}
                    <br><span>Moyenne : ${p.moyenne_annuelle ?? '—'}${p.decision ? ' · ' + escapeHTML(p.decision) : ''}</span>
                </div>
            `).join('')
            : '<p class="table-empty-state">Aucune inscription enregistrée.</p>';
    }

    const docsGrid = document.getElementById('fiche-documents-grid');
    if (docsGrid) {
        docsGrid.innerHTML = documents.map((d) => `
            <div class="fiche-doc-item ${d.fourni ? 'fourni' : 'manquant'}">
                <span>${d.fourni ? '✓' : '⚠'} ${escapeHTML(d.libelle)}${d.obligatoire ? ' *' : ''}</span>
                <span class="fiche-doc-status">${d.fourni ? 'Fourni' : 'Manquant'}</span>
                ${d.fourni ? `
                    <div class="fiche-doc-actions">
                        ${linkHTML('document-view-url-template', eleveId, d.id, 'Voir le document', 'target="_blank" rel="noopener"', viewIcon())}
                        ${linkHTML('document-download-url-template', eleveId, d.id, 'Télécharger le document', '', downloadIcon())}
                        ${deleteFormHTML('document-delete-url-template', eleveId, d.id, `Supprimer le document « ${d.libelle} » ?`)}
                    </div>
                ` : ''}
            </div>
        `).join('');
    }
}

/**
 * Builds a plain GET `<a>` link (view / download) for a document card
 * rendered from JS data, using the same URL-template mechanism as
 * deleteFormHTML below.
 */
function linkHTML(templateKey, eleveId, itemId, label, extraAttrs, icon) {
    if (!eleveId || !itemId) {
        return '';
    }

    const fichePanel = document.querySelector('[data-panel="fiche"]');
    const template = fichePanel?.dataset[toCamelCase(templateKey)];
    if (!template) {
        return '';
    }

    const url = template.replace('__EID__', eleveId).replace('__DID__', itemId);

    return `<a href="${escapeHTML(url)}" class="fiche-item-btn" title="${escapeHTML(label)}" aria-label="${escapeHTML(label)}" ${extraAttrs}>${icon}</a>`;
}

/**
 * Builds the "Modifier" pencil button on a tuteur card. It only carries the
 * data the edit-tuteur panel needs to prefill itself (data-edit-* attrs) —
 * the actual click handling (opening the panel, populating its fields) is
 * wired via event delegation in eleve-tuteur-document.js, since this button
 * doesn't exist yet when that script's init-time listeners are attached.
 */
function editTuteurTriggerHTML(eleveId, parent) {
    if (!eleveId || !parent.id) {
        return '';
    }

    const fichePanel = document.querySelector('[data-panel="fiche"]');
    const template = fichePanel?.dataset.tuteurUpdateUrlTemplate;
    if (!template) {
        return '';
    }

    const url = template.replace('__EID__', eleveId).replace('__PID__', parent.id);

    return `
        <button type="button" class="fiche-item-btn" title="Modifier" aria-label="Modifier"
            data-edit-tuteur-trigger
            data-edit-url="${escapeHTML(url)}"
            data-edit-nom-prenom="${escapeHTML(parent.nom ?? '')}"
            data-edit-lien="${escapeHTML(parent.lien ?? '')}"
            data-edit-telephone="${escapeHTML(parent.telephone ?? '')}"
            data-edit-email="${escapeHTML(parent.email ?? '')}"
        >${editIcon()}</button>
    `;
}

function editIcon() {
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
}

function viewIcon() {
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function downloadIcon() {
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>';
}

/**
 * Builds an inline POST form (method-spoofed DELETE) for a "supprimer"
 * button rendered from JS data (tuteur card / document card). Uses the
 * URL template stored on the fiche panel's dataset (`__EID__`/`__PID__` or
 * `__EID__`/`__DID__` placeholders) and the CSRF meta tag added to the
 * layout head, since this markup isn't server-rendered per item.
 */
function deleteFormHTML(templateKey, eleveId, itemId, confirmMessage) {
    if (!eleveId || !itemId) {
        return '';
    }

    const fichePanel = document.querySelector('[data-panel="fiche"]');
    const template = fichePanel?.dataset[toCamelCase(templateKey)];
    if (!template) {
        return '';
    }

    const url = template
        .replace('__EID__', eleveId)
        .replace('__PID__', itemId)
        .replace('__DID__', itemId);

    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return `
        <form method="POST" action="${escapeHTML(url)}" class="fiche-item-delete-form" onsubmit="return confirm('${escapeHTML(confirmMessage).replace(/'/g, '&#39;')}')">
            <input type="hidden" name="_token" value="${escapeHTML(token)}">
            <input type="hidden" name="_method" value="DELETE">
            <button type="submit" class="fiche-item-btn fiche-item-delete" title="Supprimer" aria-label="Supprimer">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0-1 14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 6h14ZM10 11v6M14 11v6"/></svg>
            </button>
        </form>
    `;
}

function toCamelCase(kebab) {
    return kebab.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
}

function fieldHTML(label, value) {
    return `<div class="fiche-field"><span class="flabel">${escapeHTML(label)}</span><span class="fvalue">${escapeHTML(String(value))}</span></div>`;
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
