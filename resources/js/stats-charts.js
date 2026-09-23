import { Chart, BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip } from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Legend, Tooltip);

// Couleurs reprises des variables CSS du thème (voir design-system.css) —
// dupliquées ici en dur : Chart.js dessine sur un <canvas>, hors du DOM
// stylable en CSS, donc getComputedStyle() sur un élément voisin serait la
// seule alternative, plus fragile qu'une simple recopie de ces 4 teintes.
const COULEURS = {
    vert: '#3D8B75',
    vertTeinte: '#E5F1EC',
    or: '#EFA03B',
    orTeinte: '#FCEEDA',
    bleu: '#3B6EA5',
    bleuTeinte: '#E7EEF6',
};

/**
 * "Statistiques" (onglet de l'écran Rapports, Direction uniquement) :
 * effectifs d'apprenants par année académique, toutes années du système —
 * voir RapportService::effectifsParAnnee() et RapportController::index().
 * Barres simples : une seule série, pas besoin de légende.
 */
export function initRapportsEffectifsChart() {
    const canvas = document.getElementById('chart-effectifs-annees');
    if (!canvas) {
        return;
    }

    const donnees = JSON.parse(canvas.dataset.effectifs ?? '[]');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: donnees.map((d) => d.libelle),
            datasets: [{
                label: 'Apprenants actifs',
                data: donnees.map((d) => d.effectif),
                backgroundColor: COULEURS.vertTeinte,
                borderColor: COULEURS.vert,
                borderWidth: 1.5,
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}

/**
 * Statistiques des évaluations (onglet Examens de la fiche année académique,
 * academique.annees.show) : taux de complétion des notes et moyenne de
 * classe, un point par évaluation mensuelle de l'année — voir
 * RapportService::statistiquesEvaluations() et AnneeAcademiqueController::show().
 * Deux échelles (% à gauche, /20 à droite) puisque les deux séries n'ont pas
 * la même unité.
 */
export function initExamensStatistiquesChart() {
    const canvas = document.getElementById('chart-examens-statistiques');
    if (!canvas) {
        return;
    }

    const donnees = JSON.parse(canvas.dataset.stats ?? '[]');

    new Chart(canvas, {
        data: {
            labels: donnees.map((d) => d.libelle),
            datasets: [
                {
                    type: 'bar',
                    label: 'Taux de complétion des notes (%)',
                    data: donnees.map((d) => d.taux_completion),
                    backgroundColor: COULEURS.bleuTeinte,
                    borderColor: COULEURS.bleu,
                    borderWidth: 1.5,
                    borderRadius: 4,
                    yAxisID: 'pourcentage',
                },
                {
                    type: 'line',
                    label: 'Moyenne de classe (/20)',
                    data: donnees.map((d) => d.moyenne),
                    borderColor: COULEURS.or,
                    backgroundColor: COULEURS.or,
                    tension: 0.25,
                    spanGaps: true,
                    yAxisID: 'moyenne',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                pourcentage: { type: 'linear', position: 'left', min: 0, max: 100, title: { display: true, text: '%' } },
                moyenne: { type: 'linear', position: 'right', min: 0, max: 20, grid: { drawOnChartArea: false }, title: { display: true, text: '/20' } },
            },
        },
    });
}
