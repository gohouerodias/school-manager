import { togglePanel } from './slide-panel';

/**
 * Opens the singleton <x-image-lightbox id="image-lightbox"> (see
 * layouts/app.blade.php) showing `url` full-size. No matching init function
 * is needed here: closing it (✕ button, overlay click) is already handled
 * generically by slide-panel.js's initSlidePanels(), the same as any
 * <x-slide-panel>/<x-fiche-modal>/<x-confirm-modal> instance.
 */
export function openImageLightbox(url, alt = '', modalId = 'image-lightbox') {
    const img = document.getElementById(`${modalId}-img`);
    if (!img || !url) {
        return;
    }

    img.src = url;
    img.alt = alt;
    togglePanel(modalId, true);
}
