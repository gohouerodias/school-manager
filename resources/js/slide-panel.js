/**
 * Generic open/close for any <x-slide-panel> instance. Trigger elements use
 * data-panel-open="{id}"; the panel and its overlay share data-panel="{id}"
 * / data-panel-overlay="{id}". Reusable for any future create/edit panel.
 */
export function initSlidePanels() {
    document.querySelectorAll('[data-panel-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => togglePanel(trigger.dataset.panelOpen, true));
    });

    document.querySelectorAll('[data-panel-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => togglePanel(trigger.dataset.panelClose, false));
    });

    document.querySelectorAll('[data-panel-overlay]').forEach((overlay) => {
        overlay.addEventListener('click', () => togglePanel(overlay.dataset.panelOverlay, false));
    });
}

export function togglePanel(id, show) {
    document.querySelector(`[data-panel="${id}"]`)?.classList.toggle('show', show);
    document.querySelector(`[data-panel-overlay="${id}"]`)?.classList.toggle('show', show);
}
