/**
 * Session expirée → page de connexion, automatiquement.
 *
 * Sans cela, une page laissée ouverte longtemps (toute une nuit…) reste
 * affichée alors que la session a expiré côté serveur : la première action
 * (enregistrer des notes, envoyer un formulaire) échoue avec un message
 * d'erreur et il faut recharger soi-même pour retrouver la connexion.
 *
 * Deux mécanismes complémentaires :
 *  1. Minuteur : la page connaît la durée de vie de la session
 *     (<meta name="session-lifetime">, en minutes — config/session.php). Si
 *     elle est dépassée depuis la dernière requête (au retour sur l'onglet ou
 *     en tâche de fond), la page se recharge : le serveur redirige alors
 *     vers la connexion avec le message « Votre session a expiré ».
 *  2. Requêtes en arrière-plan (fetch) : une réponse 401 (non connecté) ou
 *     419 (jeton expiré) recharge aussi la page, au lieu d'afficher une
 *     erreur incompréhensible.
 *
 * Uniquement sur les pages connectées : la page de connexion n'a pas cette
 * balise meta, ce module n'y fait donc rien.
 */
export function initSessionExpiree() {
    const meta = document.querySelector('meta[name="session-lifetime"]');
    const dureeMinutes = Number(meta?.content || 0);

    if (!dureeMinutes) {
        return;
    }

    // Petite marge : on recharge juste après l'expiration côté serveur.
    const dureeMs = dureeMinutes * 60 * 1000 + 15 * 1000;
    let derniereActivite = Date.now();
    let rechargementEnCours = false;

    const recharger = () => {
        if (rechargementEnCours) {
            return;
        }
        rechargementEnCours = true;
        window.location.reload();
    };

    const verifier = () => {
        if (Date.now() - derniereActivite > dureeMs) {
            recharger();
        }
    };

    // Chaque requête en arrière-plan prolonge la session côté serveur : on
    // repart de là. Une réponse 401/419 signifie qu'elle a déjà expiré.
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const reponse = await fetchOriginal(...args);
        if (reponse.status === 401 || reponse.status === 419) {
            recharger();
        } else {
            derniereActivite = Date.now();
        }

        return reponse;
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            verifier();
        }
    });
    window.addEventListener('focus', verifier);
    window.setInterval(verifier, 60 * 1000);
}
