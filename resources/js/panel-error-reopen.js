/**
 * Reopens the <x-slide-panel>/<x-fiche-modal> the user was submitting when a
 * validation error redirected them back (see the "reopen-panel" meta tag in
 * layouts/app.blade.php, fed by each form's hidden `_panel` input via
 * `old('_panel')`). Without this, panels are `transform: translateX(100%)`
 * off-screen by default, so the @error messages inside them would be
 * rendered but invisible.
 */
export function initPanelErrorReopen() {
    const panelId = document.querySelector('meta[name="reopen-panel"]')?.content;

    if (!panelId) {
        return;
    }

    document.querySelector(`[data-panel="${panelId}"]`)?.classList.add('show');
    document.querySelector(`[data-panel-overlay="${panelId}"]`)?.classList.add('show');
}
