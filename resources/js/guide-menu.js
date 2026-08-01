/**
 * Toggle for the topbar "Guide d'utilisation" popover (<x-guide-menu>):
 * opens on trigger click, closes on outside click. Same pattern as
 * profile-menu.js.
 */
export function initGuideMenu() {
    document.querySelectorAll('[data-guide-menu]').forEach((wrap) => {
        const trigger = wrap.querySelector('[data-guide-menu-trigger]');
        const panel = wrap.querySelector('[data-guide-menu-panel]');

        if (!trigger || !panel) {
            return;
        }

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = panel.classList.contains('show');
            closeAllGuideMenus();
            if (!isOpen) {
                panel.classList.add('show');
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-guide-menu]')) {
            closeAllGuideMenus();
        }
    });
}

function closeAllGuideMenus() {
    document.querySelectorAll('[data-guide-menu-panel].show').forEach((panel) => panel.classList.remove('show'));
}
