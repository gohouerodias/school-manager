/**
 * Left rail navigation (<x-sidebar-nav>) toggle.
 *
 * Desktop (mirrors files/gestion-comptes2.html's toggleSidebar()): the
 * header's own burger button shrinks the rail to icons-only via the
 * .collapsed class.
 *
 * Mobile (see app-shell.css's 880px breakpoint): the same rail becomes an
 * off-canvas drawer instead — hidden by default, opened via the topbar's
 * burger button ([data-sidebar-open]), and closed via its own header
 * button, the backdrop, or Escape.
 */
export function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const railToggle = document.querySelector('[data-sidebar-toggle]');
    const openToggle = document.querySelector('[data-sidebar-open]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const isMobile = () => window.matchMedia('(max-width: 880px)').matches;

    const openDrawer = () => {
        sidebar?.classList.add('mobile-open');
        backdrop?.classList.add('show');
    };
    const closeDrawer = () => {
        sidebar?.classList.remove('mobile-open');
        backdrop?.classList.remove('show');
    };

    if (sidebar && railToggle) {
        railToggle.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.contains('mobile-open') ? closeDrawer() : openDrawer();
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    openToggle?.addEventListener('click', openDrawer);
    backdrop?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

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
