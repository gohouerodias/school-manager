/**
 * Collapse/expand toggle for the left rail navigation (<x-sidebar-nav>),
 * mirroring files/gestion-comptes2.html's toggleSidebar(). Purely visual:
 * shrinks the rail to icons-only via the .collapsed class.
 */
export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    if (sidebar && toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    }

    initNavGroups();
}

/**
 * Expand/collapse a <div data-nav-group> submenu (e.g. "Dossier élève et
 * documents" > "Liste des apprenants" / "Paramètres des dossiers"). Purely
 * visual: the submenu is server-rendered open already when the current
 * route falls under it.
 */
function initNavGroups() {
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const toggle = group.querySelector('[data-nav-parent-toggle]');
        const submenu = group.querySelector('[data-nav-submenu]');

        if (!toggle || !submenu) {
            return;
        }

        toggle.addEventListener('click', () => {
            const isOpen = submenu.style.display === 'block';
            submenu.style.display = isOpen ? 'none' : 'block';
            toggle.setAttribute('aria-expanded', String(!isOpen));
        });
    });
}
