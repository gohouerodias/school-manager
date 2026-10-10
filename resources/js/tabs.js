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

    // Dernier onglet ouvert sur cette page : après une action (ajout d'une
    // matière, création d'un examen…), la page se recharge sur le même
    // onglet au lieu de revenir au premier.
    const cleMemoire = `onglet-actif:${window.location.pathname}`;

    function activate(tab) {
        try {
            sessionStorage.setItem(cleMemoire, tab);
        } catch {
            // Stockage indisponible (navigation privée…) : sans conséquence.
        }
        // L'adresse suit l'onglet ouvert (?onglet=…) : un retour après une
        // action (back() côté serveur) revient sur cet onglet, et le menu
        // latéral surligne la bonne entrée (Examens, Affectations…).
        const url = new URL(window.location.href);
        if (url.searchParams.get('onglet') !== tab) {
            url.searchParams.set('onglet', tab);
            window.history.replaceState(window.history.state, '', url);
        }
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
    let ongletMemorise = null;
    try {
        ongletMemorise = sessionStorage.getItem(cleMemoire);
    } catch {
        ongletMemorise = null;
    }
    const ongletDemande = new URLSearchParams(window.location.search).get('onglet') || ongletMemorise;
    if (ongletDemande && Array.from(buttons).some((btn) => btn.dataset.tabBtn === ongletDemande)) {
        activate(ongletDemande);
    }
}
