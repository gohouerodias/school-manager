/**
 * Écran "Aperçu bulletin" (resources/views/eleves/bulletins/apercu.blade.php) :
 * le bouton « Partager » essaie d'abord le partage natif du fichier PDF
 * (Web Share API, navigator.share avec `files` — ouvre le sélecteur du
 * système : email, WhatsApp, etc. selon la plateforme). Quand cette API
 * n'est pas disponible (essentiellement les navigateurs de bureau), on
 * retombe sur un petit menu : le PDF est téléchargé localement puis un lien
 * mailto:/wa.me pré-rempli s'ouvre — un lien de téléchargement de ce site
 * (authentifié) ne peut de toute façon pas être partagé tel quel à un tiers.
 */
export function initBulletinApercu() {
    const shareBtn = document.getElementById('bulletin-partager-btn');
    const menu = document.getElementById('bulletin-share-menu');
    if (!shareBtn || !menu) {
        return;
    }

    const { shareUrl, shareTitle, shareText } = shareBtn.dataset;

    function declencherTelechargement() {
        const a = document.createElement('a');
        a.href = shareUrl;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    async function partagerFichierNatif() {
        if (!navigator.share) {
            return false;
        }

        try {
            const response = await fetch(shareUrl, { credentials: 'same-origin' });
            const blob = await response.blob();
            const file = new File([blob], `${shareTitle}.pdf`, { type: 'application/pdf' });

            if (navigator.canShare && !navigator.canShare({ files: [file] })) {
                return false;
            }

            await navigator.share({ files: [file], title: shareTitle, text: shareText });

            return true;
        } catch (error) {
            // Partage annulé par l'utilisateur, ou API indisponible pour ce
            // fichier — on retombe silencieusement sur le menu ci-dessous.
            return error && error.name === 'AbortError';
        }
    }

    shareBtn.addEventListener('click', async (event) => {
        event.preventDefault();

        const partage = await partagerFichierNatif();
        if (!partage) {
            menu.hidden = !menu.hidden;
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.hidden && event.target !== shareBtn && !menu.contains(event.target) && !shareBtn.contains(event.target)) {
            menu.hidden = true;
        }
    });

    menu.querySelectorAll('[data-share-channel]').forEach((lien) => {
        lien.addEventListener('click', (event) => {
            event.preventDefault();
            declencherTelechargement();

            if (lien.dataset.shareChannel === 'email') {
                const corps = `${shareText}\n\n(Le bulletin PDF vient d'être téléchargé — pensez à le joindre à cet email.)`;
                window.location.href = `mailto:?subject=${encodeURIComponent(shareTitle)}&body=${encodeURIComponent(corps)}`;
            } else if (lien.dataset.shareChannel === 'whatsapp') {
                const texte = `${shareText} (fichier PDF téléchargé, à joindre au message)`;
                window.open(`https://wa.me/?text=${encodeURIComponent(texte)}`, '_blank', 'noopener');
            }

            menu.hidden = true;
        });
    });
}
