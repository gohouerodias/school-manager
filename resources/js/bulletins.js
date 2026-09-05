/**
 * Écran admin "Bulletins" (resources/views/eleves/bulletins/index.blade.php) :
 * la génération démarre immédiatement en arrière-plan sur la file d'attente
 * (voir App\Jobs\GenererBulletinsClasseJob) plutôt que dans la requête HTTP
 * du bouton « Générer » — tant que la demande est en_attente/en_cours, on
 * interroge périodiquement eleves.bulletins.statut pour afficher une
 * progression en temps réel sans jamais recharger la page ni bloquer le
 * serveur pendant le traitement d'une classe entière.
 */
export function initBulletinsGeneration() {
    const status = document.getElementById('generation-status');
    if (!status) {
        return;
    }

    const url = status.dataset.statutUrl;
    const generateBtn = document.getElementById('generate-bulletins-btn');
    const enCours = (statut) => statut === 'en_attente' || statut === 'en_cours';

    // Une demande restée `en_attente` plus de 2 minutes sans qu'aucun worker
    // ne l'ait prise en charge ne doit plus bloquer le bouton indéfiniment —
    // voir DemandeGenerationBulletin::estCoinceeSansWorker() côté serveur,
    // dont ce booléen est le reflet exact (statut() l'expose pour ça).
    const bloque = (data) => enCours(data.statut) && !data.coinceeSansWorker;

    const render = (data) => {
        status.classList.toggle('error', data.echec || data.coinceeSansWorker);

        if (data.genere) {
            status.innerHTML = `✓ Bulletins générés le ${data.genereAt} (${data.nbBulletinsGeneres} bulletin(s)).`
                + (data.telechargerUrl ? ` <a href="${data.telechargerUrl}">Télécharger le PDF groupé</a>` : '');
        } else if (data.echec) {
            status.innerHTML = `✕ La génération a échoué : ${data.erreur ?? 'erreur inconnue.'}`;
        } else if (data.coinceeSansWorker) {
            status.innerHTML = '⚠ La génération semble bloquée depuis plus de 2 minutes — aucun worker de file d\'attente ne semble actif (voir <code>php artisan queue:work</code> ou <code>composer run dev</code>). Vous pouvez relancer la génération ci-dessous.';
        } else {
            status.innerHTML = `
                <div class="generation-progress-row">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="generation-progress-text">${data.label} — ${data.traites} / ${data.total} bulletin(s) traité(s)</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:${data.pourcentage}%;"></div></div>
            `;
        }

        if (generateBtn) {
            generateBtn.disabled = bloque(data);
        }
    };

    const poll = () => {
        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((data) => {
                render(data);

                // Inutile de continuer à interroger le serveur une fois
                // coincée : rien ne changera avant qu'un worker démarre ou
                // qu'une nouvelle génération soit demandée (rechargement de
                // page via le formulaire).
                if (bloque(data)) {
                    setTimeout(poll, 1500);
                }
            })
            .catch(() => {
                // Le worker peut redémarrer ou le réseau flancher un instant —
                // on retente sans jamais figer l'écran ni afficher d'erreur.
                setTimeout(poll, 3000);
            });
    };

    if (enCours(status.dataset.statutInitial)) {
        if (generateBtn) {
            generateBtn.disabled = status.dataset.bloquantInitial === '1';
        }
        poll();
    }
}
