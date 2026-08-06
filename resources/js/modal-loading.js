/**
 * Big, animated loading overlay shown inside any modal/panel while it fetches
 * data — e.g. the "Consulter la fiche" modal's `GET .../fiche` call (see
 * eleve-fiche.js). Reusable from any feature that opens a panel and then
 * fetches its content asynchronously, just by wrapping the fetch:
 *
 *   const hide = showModalLoading(document.querySelector('[data-panel="fiche"]'));
 *   fetch(url).then(...).finally(hide);
 *
 * The overlay is absolutely positioned over its container, so the container
 * needs a positioning context — every `.panel` variant already has one
 * (`position: fixed`), so this works unmodified on any of them.
 */
export function showModalLoading(container) {
    if (!container) {
        return () => {};
    }

    const overlay = document.createElement('div');
    overlay.className = 'modal-loading-overlay';
    overlay.dataset.modalLoading = 'true';
    overlay.innerHTML = `
        <div class="modal-loading-spinner" aria-hidden="true"><span></span><span></span><span></span></div>
        <p class="modal-loading-text">Chargement…</p>
    `;

    container.appendChild(overlay);

    return () => overlay.remove();
}
