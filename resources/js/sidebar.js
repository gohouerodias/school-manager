/**
 * Collapse/expand toggle for the left rail navigation (<x-sidebar-nav>),
 * mirroring files/gestion-comptes2.html's toggleSidebar(). Purely visual:
 * shrinks the rail to icons-only via the .collapsed class.
 */
export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    if (!sidebar || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
}
