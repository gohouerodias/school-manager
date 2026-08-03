/**
 * Generic "⋯" row-actions dropdown, used by <x-action-menu>. Reusable for
 * any table row across the app.
 *
 * The menu is `position: fixed` and positioned in JS from the trigger's
 * bounding rect (rather than `position: absolute` inside the row) so it
 * isn't clipped by `.data-card { overflow: hidden; }` — that clip is what
 * made the dropdown appear cut off for rows near the bottom/right of a
 * table.
 *
 * The trigger click is delegated on `document` (rather than bound directly
 * to each `[data-action-menu-trigger]` at init time) so rows swapped in
 * later — e.g. by the élèves list's live search, see live-search.js — keep
 * working without needing to re-run this init function.
 */
export function initActionMenus() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-action-menu-trigger]');
        if (trigger) {
            event.stopPropagation();
            const menu = trigger.nextElementSibling;
            const isOpen = menu.classList.contains('show');
            closeAllMenus();
            if (!isOpen) {
                openMenu(trigger, menu);
            }
            return;
        }

        if (!event.target.closest('[data-action-menu]')) {
            closeAllMenus();
        }
    });

    // Scrolling (page or any scrollable ancestor) or resizing the window
    // would leave a `position: fixed` menu floating over the wrong row, so
    // just close it instead of tracking its position.
    window.addEventListener('scroll', closeAllMenus, true);
    window.addEventListener('resize', closeAllMenus);
}

function openMenu(trigger, menu) {
    menu.classList.add('show');

    const rect = trigger.getBoundingClientRect();
    const menuWidth = menu.offsetWidth;
    const menuHeight = menu.offsetHeight;

    let top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - 8) {
        // Not enough room below the trigger: open the menu upward instead.
        top = Math.max(8, rect.top - menuHeight - 6);
    }

    const left = Math.min(
        Math.max(8, rect.right - menuWidth),
        window.innerWidth - menuWidth - 8
    );

    menu.style.top = `${top}px`;
    menu.style.left = `${left}px`;
}

function closeAllMenus() {
    document.querySelectorAll('[data-action-menu-list].show').forEach((menu) => {
        menu.classList.remove('show');
        menu.style.top = '';
        menu.style.left = '';
    });
}
