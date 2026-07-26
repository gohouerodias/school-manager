/**
 * Auto-hides the <x-flash-toast> banner a few seconds after it appears.
 */
export function initFlashToast() {
    const toast = document.querySelector('[data-flash-toast]');

    if (!toast) {
        return;
    }

    setTimeout(() => toast.classList.remove('show'), 3200);
}
