/**
 * Écran admin "Bulletins" (resources/views/eleves/bulletins/index.blade.php) :
 * la génération démarre immédiatement en arrière-plan sur la file d'attente
 * (voir App\Jobs\GenererBulletinsClasseJob / GenererBulletinsAnnuelsClasseJob)
 * plutôt que dans la requête HTTP du bouton « Générer » — tant que la
 * demande est en_attente/en_cours, on interroge périodiquement l'URL de
 * statut pour afficher une progression en temps réel sans jamais recharger
 * la page ni bloquer le serveur pendant le traitement d'une classe entière.
 *
 * initGenerationStatus() est générique (ids de statusEl/generateBtn passés en
 * paramètre) car l'écran affiche désormais DEUX suivis indépendants sur la
 * même page : la génération mensuelle (#generation-status /
 * #generate-bulletins-btn) et la génération annuelle (#generation-status-
 * annuel / #generate-bulletins-annuel-btn) — voir initBulletinsGeneration()
 * et initBulletinsAnnuelsGeneration() ci-dessous, chacune un simple appel de
 * cette fonction avec ses propres ids/libellés.
 */
function initGenerationStatus({ statusId, generateBtnId, unite }) {
    const status = document.getElementById(statusId);
    if (!status) {
        return;
    }

    const url = status.dataset.statutUrl;
    const generateBtn = document.getElementById(generateBtnId);
    const enCours = (statut) => statut === 'en_attente' || statut === 'en_cours';

    // Une demande restée `en_attente` plus de 2 minutes sans qu'aucun worker
    // ne l'ait prise en charge ne doit plus bloquer le bouton indéfiniment —
    // voir DemandeGenerationBulletin(Annuel)::estCoinceeSansWorker() côté
    // serveur, dont ce booléen est le reflet exact (statut() l'expose pour ça).
    const bloque = (data) => enCours(data.statut) && !data.coinceeSansWorker;

    const render = (data) => {
        status.classList.toggle('error', data.echec || data.coinceeSansWorker);

        if (data.genere) {
            status.innerHTML = `✓ Bulletins générés le ${escapeHTML(data.genereAt)} (${escapeHTML(data.nbBulletinsGeneres)} ${unite}(s)).`
                + (data.telechargerUrl ? ` <a href="${escapeHTML(data.telechargerUrl)}" data-no-loader>Télécharger le PDF groupé</a>` : '');
        } else if (data.echec) {
            status.innerHTML = `✕ La génération a échoué : ${escapeHTML(data.erreur ?? 'erreur inconnue.')}`;
        } else if (data.coinceeSansWorker) {
            status.textContent = "⚠ La génération n'a pas démarré ou a été interrompue par le serveur (aucune progression depuis plus de 2 minutes). Relancez-la ci-dessous ; si le problème persiste, contactez l'administrateur technique.";
        } else {
            status.innerHTML = `
                <div class="generation-progress-row">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="generation-progress-text">${escapeHTML(data.label)} — ${Number(data.traites)} / ${Number(data.total)} ${unite}(s) traité(s)</span>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:${Number(data.pourcentage) || 0}%;"></div></div>
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

export function initBulletinsGeneration() {
    initGenerationStatus({ statusId: 'generation-status', generateBtnId: 'generate-bulletins-btn', unite: 'bulletin' });
}

export function initBulletinsAnnuelsGeneration() {
    initGenerationStatus({ statusId: 'generation-status-annuel', generateBtnId: 'generate-bulletins-annuel-btn', unite: 'bulletin' });
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
