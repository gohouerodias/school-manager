import { openImageLightbox } from './image-lightbox';
import { showModalLoading } from './modal-loading';

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

        const fichePanelEl = document.querySelector('[data-panel="fiche"]');
        const hideLoading = showModalLoading(fichePanelEl);

        fetch(trigger.dataset.ficheUrl, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then(renderFiche)
            .catch(() => {})
            .finally(hideLoading);

        fichePanelEl?.classList.add('show');
        document.querySelector('[data-panel-overlay="fiche"]')?.classList.add('show');
    });

    // #fiche-avatar-img is a static, persistent element (its `src` is just
    // updated on every renderFiche() call), so a plain one-time listener is
    // enough — unlike the delegated listeners above, it doesn't need to
    // survive rows being replaced by live search. It's only ever visible
    // (see renderFiche()) when there's a real photo to show.
    document.getElementById('fiche-avatar-img')?.addEventListener('click', (event) => {
        const src = event.currentTarget.getAttribute('src');
        if (src) {
            openImageLightbox(src, "Photo d'identité");
        }
    });

    // eleves.index?fiche={id} (see EleveController::index()) renders a
    // hidden [data-fiche-autoopen] trigger: open that fiche on page load.
    document.querySelector('[data-fiche-autoopen]')?.click();

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
    const eleveId = document.querySelector('[data-panel="fiche"]')?.dataset.currentEleveId;

    setText('fiche-nom-famille', identite.nom);
    setText('fiche-prenom', identite.prenom);
    // Hidden rather than shown blank when the matricule (Educmaster, typed
    // in manually) hasn't been entered yet — a badge reading nothing looks
    // like a rendering glitch.
    const matriculeBadge = document.getElementById('fiche-matricule');
    if (matriculeBadge) {
        matriculeBadge.textContent = identite.matricule ?? '';
        matriculeBadge.style.display = identite.matricule ? 'inline-block' : 'none';
    }
    // Internal "CSC-{id}" identifier (Eleve::identifiantVirtuel()) — unlike
    // the matricule above, it's derived from the élève's own id, so it's
    // always available and always shown, even before an official Educmaster
    // matricule has been entered.
    setText('fiche-identifiant-virtuel', identite.identifiant_virtuel ?? '');
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
            ['Début de scolarité', identite.date_debut_scolarite || 'Non renseignée'],
        ];
        if (identite.statut === 'archive') {
            fixedFields.push(['Archivée le', identite.date_archivage || '—']);
            fixedFields.push(["Motif de l'archivage", identite.motif_archivage || 'Non renseigné']);
        }
        // "Classe désirée" (niveau souhaité) is only shown while the élève
        // has no fixed classe yet — once a real Inscription exists, the
        // niveau souhaité is no longer relevant.
        if (!identite.a_une_classe) {
            fixedFields.push(['Classe désirée', identite.niveau_souhaite || 'Non précisé']);
        }
        const allFields = fixedFields.concat((identite.champs || []).map((c) => [c.libelle, c.valeur || '—']));
        grid.innerHTML = allFields.map(([label, value]) => fieldHTML(label, value)).join('');
    }

    populateEditEleveTrigger(eleveId, identite);

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
            ? parcours.map(friseItemHTML).join('')
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
                        ${linkHTML('document-download-url-template', eleveId, d.id, 'Télécharger le document', 'data-no-loader', downloadIcon())}
                        ${deleteFormHTML('document-delete-url-template', eleveId, d.id, `Supprimer le document « ${d.libelle} » ?`)}
                    </div>
                ` : ''}
            </div>
        `).join('');
    }
}

/**
 * One row of the "Parcours scolaire" tab's frise chronologique — one per
 * Inscription (see Eleves\EleveController::fiche()'s `parcours` payload).
 * Unlike the parents/documents cards, this one doesn't need a URL template:
 * the backend already returns a ready-to-use `statut_update_url` per row.
 */
function friseItemHTML(p) {
    const badges = [];

    if (p.annee_active) {
        badges.push('<span class="frise-badge badge-active">Année en cours</span>');
    }
    if (p.statut_notable) {
        badges.push(`<span class="frise-badge badge-statut-${escapeHTML(p.statut)}">${statutIcon(p.statut)} ${escapeHTML(p.statut_label)}</span>`);
    }
    if (p.decision) {
        const moyenneSuffix = (p.moyenne_annuelle ?? null) !== null ? ` (${formatMoyenne(p.moyenne_annuelle)}/20)` : '';
        badges.push(`<span class="frise-badge badge-decision-${escapeHTML(p.decision)}">${p.decision === 'admis' ? '✓' : '✕'} ${escapeHTML(p.decision_label)}${moyenneSuffix}</span>`);
    }

    const metaParts = [];
    if (p.annee_active && !p.decision && (p.moyenne_annuelle ?? null) !== null) {
        metaParts.push(`Moyenne actuelle : ${formatMoyenne(p.moyenne_annuelle)}/20`);
    }
    metaParts.push(`Titulaire : ${p.titulaire ? escapeHTML(p.titulaire) : '—'}`);

    return `
        <div class="frise-item ${p.annee_active ? 'active' : ''}">
            <div class="frise-marker"></div>
            <div class="frise-content">
                <div class="frise-header">
                    <b>${escapeHTML(p.annee ?? '—')}</b> — ${escapeHTML(p.classe ?? 'Sans classe')}
                    ${badges.join(' ')}
                    ${editStatutTriggerHTML(p)}
                </div>
                <div class="frise-meta">${metaParts.join(' · ')}</div>
                ${friseDocumentsHTML(p)}
            </div>
        </div>
    `;
}

/**
 * Documents justifiant cette inscription précise — surtout utile pour
 * "Transféré entrant" : la preuve que l'élève vient bien d'une autre école
 * (voir DocumentNumerique::inscription() et EleveController::fiche()'s
 * `documents`/`document_upload_url` par item de parcours). Volontairement
 * limité à ce seul statut pour l'instant plutôt qu'à tout statut_notable —
 * c'est le seul cas demandé.
 */
function friseDocumentsHTML(p) {
    if (p.statut !== 'transfert_entrant') {
        return '';
    }

    const documentsList = (p.documents ?? []).map((d) => `
        <span class="frise-document-item">
            <a href="${escapeHTML(d.view_url)}" target="_blank" rel="noopener" title="Voir « ${escapeHTML(d.libelle)} »">${viewIcon()}</a>
            <a href="${escapeHTML(d.download_url)}" title="Télécharger « ${escapeHTML(d.libelle)} »">${downloadIcon()}</a>
            ${escapeHTML(d.libelle)}
        </span>
    `).join('');

    const addButton = peutModifier() && p.document_upload_url ? `
        <button type="button" class="frise-add-document-btn"
            data-add-document-frise-trigger
            data-upload-url="${escapeHTML(p.document_upload_url)}"
            data-inscription-id="${escapeHTML(String(p.inscription_id ?? ''))}"
            data-annee="${escapeHTML(p.annee ?? '—')}"
        >+ Document justificatif</button>
    ` : '';

    if (!documentsList && !addButton) {
        return '';
    }

    return `<div class="frise-documents">${documentsList}${addButton}</div>`;
}

/**
 * Direction has read-only access to the fiche élève (see routes/eleves.php
 * and eleves/index.blade.php's `$peutModifier`, threaded here via the
 * `<x-fiche-modal data-peut-modifier>` attribute): every mutation control
 * rendered from JS (tuteur/document edit+delete, parcours statut pencil)
 * checks this before rendering, in addition to the ones already hidden
 * server-side (#fiche-edit-eleve-trigger, "Ajouter un tuteur/document").
 */
function peutModifier() {
    return document.querySelector('[data-panel="fiche"]')?.dataset.peutModifier === '1';
}

function statutIcon(statut) {
    return { redoublant: '▲', transfert_entrant: '⇥', transfert_sortant: '⇤', abandon: '⚠' }[statut] ?? '';
}

function formatMoyenne(moyenne) {
    return Number(moyenne).toFixed(2);
}

/**
 * "Modifier le statut" pencil button on a frise item — wired via event
 * delegation in eleve-tuteur-document.js's initEditStatutParcoursPanel(),
 * since (like editTuteurTriggerHTML) this button doesn't exist yet when
 * that script's init-time listeners are attached.
 */
function editStatutTriggerHTML(p) {
    if (!p.statut_update_url || !peutModifier()) {
        return '';
    }

    return `
        <button type="button" class="fiche-item-btn frise-edit-btn" title="Modifier le statut" aria-label="Modifier le statut"
            data-edit-statut-trigger
            data-edit-url="${escapeHTML(p.statut_update_url)}"
            data-edit-statut="${escapeHTML(p.statut)}"
            data-edit-annee="${escapeHTML(p.annee ?? '—')} — ${escapeHTML(p.classe ?? 'Sans classe')}"
        >${editIcon()}</button>
    `;
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
    if (!eleveId || !parent.id || !peutModifier()) {
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

/**
 * Keeps the fiche's static "Modifier" link (#fiche-edit-eleve-trigger, in the
 * Identité tab) pointed at the fiche élève wizard's "modifier" page for
 * whichever élève's fiche is currently open. Unlike editTuteurTriggerHTML()
 * below, this element isn't rebuilt on every render — it's a fixed <a> whose
 * href is just refreshed here. The wizard page prefills itself server-side
 * straight from the Eleve model, so unlike the old "Modifier la fiche" panel
 * this used to open, no per-field data-edit-* payload is needed anymore.
 */
function populateEditEleveTrigger(eleveId, identite) {
    const trigger = document.getElementById('fiche-edit-eleve-trigger');
    if (!trigger) {
        return;
    }

    const template = document.querySelector('[data-panel="fiche"]')?.dataset.editEleveUrlTemplate;
    trigger.href = template && eleveId ? template.replace('__ID__', eleveId) : '#';
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
 *
 * Confirmation is handled by the shared red "danger" confirm-modal (see
 * confirm-submit-form.js's `data-confirm-submit` wiring) rather than the
 * browser's native confirm() popup.
 */
function deleteFormHTML(templateKey, eleveId, itemId, confirmMessage) {
    if (!eleveId || !itemId || !peutModifier()) {
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
        <form method="POST" action="${escapeHTML(url)}" class="fiche-item-delete-form"
              data-confirm-submit data-confirm-danger="1" data-confirm-label="Supprimer"
              data-confirm-message="${escapeHTML(confirmMessage)}">
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
