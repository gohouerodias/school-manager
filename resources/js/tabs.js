/**
 * Generic content tabs: click a [data-tab-btn="x"] button to show the
 * matching [data-tab-panel="x"] and hide the others. One global group per
 * page for now (mirrors how the fiche élève wizard's [data-wizard-step-btn]
 * works) — see academique/annees/show.blade.php's "Programme par niveau" /
 * "Classes" / "Affectations enseignants" tabs.
 */
export function initTabs() {
    const buttons = document.querySelectorAll('[data-tab-btn]');
    const panels = document.querySelectorAll('[data-tab-panel]');

    if (!buttons.length) {
        return;
    }

    function activate(tab) {
        buttons.forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.tabBtn === tab);
        });
        panels.forEach((panel) => {
            panel.style.display = panel.dataset.tabPanel === tab ? 'block' : 'none';
        });
    }

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => activate(btn.dataset.tabBtn));
    });

    // Deep-link support: a link ending in "?onglet=affectations" (see
    // sidebar-nav.blade.php's "Affectation des enseignants" shortcut) opens
    // straight onto that tab instead of always defaulting to the first one.
    const ongletDemande = new URLSearchParams(window.location.search).get('onglet');
    if (ongletDemande && Array.from(buttons).some((btn) => btn.dataset.tabBtn === ongletDemande)) {
        activate(ongletDemande);
    }
}
