/**
 * Onglet "Bulletin annuel — Observations" (resources/views/enseignant/
 * partials/observations-annuelles.blade.php), inclus tel quel par
 * saisie-notes.blade.php (primaire/collège) ET saisie-domaines.blade.php
 * (maternelle) — d'où un module autonome plutôt qu'ajouté à enseignant.js/
 * saisie-domaines.js : il n'a besoin d'aucune donnée de leurs blobs JSON
 * respectifs (#espace-enseignant-data / #espace-maternelle-data), juste de
 * l'URL PATCH posée en data-attribute sur la table elle-même.
 *
 * Sauvegarde à la perte de focus (blur) du textarea, uniquement si la valeur
 * a changé depuis le dernier chargement/enregistrement — pas de bouton
 * "Enregistrer" séparé, contrairement au reste de l'espace enseignant, pour
 * rester léger (un seul champ texte par apprenant, sans notion de "brouillon
 * non sauvegardé" à arbitrer).
 */
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('observationsAnnuellesTable');
    if (!table || table.dataset.editable !== '1') {
        return;
    }

    const url = table.dataset.url;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    table.querySelectorAll('.observation-annuelle-input').forEach((textarea) => {
        let derniereValeurEnregistree = textarea.value;

        textarea.addEventListener('blur', () => {
            const valeur = textarea.value.trim();
            if (valeur === derniereValeurEnregistree.trim()) {
                return;
            }

            const eleveId = textarea.dataset.eleveId;
            const status = table.querySelector(`.observation-annuelle-status[data-eleve-id="${eleveId}"]`);

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ eleve_id: eleveId, observation: valeur || null }),
            })
                .then(async (response) => ({ ok: response.ok, data: await response.json().catch(() => ({})) }))
                .then(({ ok, data }) => {
                    if (!status) return;

                    if (ok) {
                        derniereValeurEnregistree = valeur;
                        status.textContent = '✓ Enregistré';
                        status.classList.remove('error');
                    } else {
                        status.textContent = data.message || "Échec de l'enregistrement.";
                        status.classList.add('error');
                    }

                    setTimeout(() => { status.textContent = ''; status.classList.remove('error'); }, 2500);
                })
                .catch(() => {
                    if (!status) return;
                    status.textContent = "Échec de l'enregistrement — vérifiez votre connexion.";
                    status.classList.add('error');
                });
        });
    });
});
