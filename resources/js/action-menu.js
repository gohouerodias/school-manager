/**
 * Generic "⋯" row-actions dropdown, used by <x-action-menu>. Reusable for
 * any table row across the app.
 */
export function initActionMenus() {
    document.querySelectorAll('[data-action-menu-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const menu = trigger.nextElementSibling;
            const isOpen = menu.classList.contains('show');
            closeAllMenus();
            if (!isOpen) {
                menu.classList.add('show');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-action-menu]')) {
            closeAllMenus();
        }
    });
}

function closeAllMenus() {
    document.querySelectorAll('[data-action-menu-list].show').forEach((menu) => menu.classList.remove('show'));
}
