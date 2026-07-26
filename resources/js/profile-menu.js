/**
 * Toggle for the topbar "Mon profil" popover (<x-profile-menu>): opens on
 * trigger click, closes on outside click. Generic enough to support more
 * than one instance per page via data-profile-menu / data-profile-menu-panel.
 */
export function initProfileMenu() {
    document.querySelectorAll('[data-profile-menu]').forEach((wrap) => {
        const trigger = wrap.querySelector('[data-profile-menu-trigger]');
        const panel = wrap.querySelector('[data-profile-menu-panel]');

        if (!trigger || !panel) {
            return;
        }

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = panel.classList.contains('show');
            closeAllProfileMenus();
            if (!isOpen) {
                panel.classList.add('show');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-profile-menu]')) {
            closeAllProfileMenus();
        }
    });
}

function closeAllProfileMenus() {
    document.querySelectorAll('[data-profile-menu-panel].show').forEach((panel) => panel.classList.remove('show'));
}
