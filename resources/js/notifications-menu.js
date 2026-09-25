/**
 * Toggle for the topbar notifications bell (<x-notifications-menu>): opens on
 * trigger click, closes on outside click. Mirrors profile-menu.js's pattern.
 */
export function initNotificationsMenu() {
    document.querySelectorAll('[data-notifications-menu]').forEach((wrap) => {
        const trigger = wrap.querySelector('[data-notifications-menu-trigger]');
        const panel = wrap.querySelector('[data-notifications-menu-panel]');

        if (!trigger || !panel) {
            return;
        }

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = panel.classList.contains('show');
            closeAllNotificationsMenus();
            if (!isOpen) {
                panel.classList.add('show');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-notifications-menu]')) {
            closeAllNotificationsMenus();
        }
    });
}

function closeAllNotificationsMenus() {
    document.querySelectorAll('[data-notifications-menu-panel].show').forEach((panel) => panel.classList.remove('show'));
}
