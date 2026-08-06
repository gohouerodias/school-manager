/**
 * Reusable "does this parent/tuteur already exist ?" quick-search: as the
 * agent types a nom et prénom, matching ParentTuteur records (same multi-mot
 * search as the "Liste des tuteurs" page) are suggested; picking one
 * prefills téléphone/email from that record and shows a confirmation
 * banner, so the record gets associated instead of duplicated.
 *
 * Wiring a new form into this feature is just one call, by naming its
 * fields with a shared prefix and passing that same prefix here — no new
 * JS needs to be written per form:
 *
 *   initTuteurQuickSearch('tuteur', { rechercheUrl });
 *
 * expects, for prefix "tuteur":
 *   #tuteur-nom-prenom   (required) the "Nom et prénom" text input
 *   #tuteur-telephone    (optional) prefilled with the match's téléphone
 *   #tuteur-email        (optional) prefilled with the match's email
 *   #tuteur-suggestions  (required) empty container for the suggestion list
 *   #tuteur-match        (required) empty container for the confirmation banner
 *   #tuteur-existing-id  (optional) hidden input set to the matched tuteur's id,
 *                        so the backend can associate it directly (see
 *                        App\Support\TuteurResolver) instead of re-guessing
 *                        from nom/prénom/téléphone alone
 *
 * Used by resources/js/eleve-tuteur-document.js (fiche modal's "Ajouter un
 * tuteur" / "Modifier le tuteur" panels) and resources/js/eleve-wizard.js
 * (étape 3's "Ajouter un parent / tuteur").
 *
 * @param {string} idPrefix
 * @param {{ rechercheUrl?: string, onSelect?: (tuteur: object) => void }} options
 * @returns {{ getMatch: () => object|null, reset: () => void }}
 */
export function initTuteurQuickSearch(idPrefix, { rechercheUrl, onSelect } = {}) {
    const nomPrenomInput = document.getElementById(`${idPrefix}-nom-prenom`);
    const telephoneInput = document.getElementById(`${idPrefix}-telephone`);
    const emailInput = document.getElementById(`${idPrefix}-email`);
    const suggestionsBox = document.getElementById(`${idPrefix}-suggestions`);
    const matchBox = document.getElementById(`${idPrefix}-match`);
    const existingIdInput = document.getElementById(`${idPrefix}-existing-id`);

    const noop = { getMatch: () => null, reset: () => {} };

    if (!nomPrenomInput || !suggestionsBox || !matchBox || !rechercheUrl) {
        return noop;
    }

    const DEBOUNCE_MS = 300;
    let debounceTimer = null;
    let matched = null;

    function clearMatch() {
        matched = null;
        if (existingIdInput) {
            existingIdInput.value = '';
        }
        matchBox.style.display = 'none';
        matchBox.innerHTML = '';
    }

    function clearSuggestions() {
        suggestionsBox.innerHTML = '';
        suggestionsBox.style.display = 'none';
    }

    function selectTuteur(tuteur) {
        matched = tuteur;
        nomPrenomInput.value = tuteur.nom_prenom;
        if (telephoneInput) {
            telephoneInput.value = tuteur.telephone || '';
        }
        if (emailInput) {
            emailInput.value = tuteur.email || '';
        }
        if (existingIdInput) {
            existingIdInput.value = tuteur.id;
        }
        clearSuggestions();

        matchBox.style.display = 'block';
        matchBox.innerHTML = `Ce tuteur existe déjà (<b>${escapeHTML(tuteur.nom_prenom)}</b>) : il/elle sera associé(e) plutôt que d'en créer un doublon.`;

        onSelect?.(tuteur);
    }

    function renderSuggestions(tuteurs) {
        if (!tuteurs.length) {
            clearSuggestions();

            return;
        }

        suggestionsBox.style.display = 'block';
        suggestionsBox.innerHTML = tuteurs
            .map(
                (tuteur, index) => `
                <button type="button" class="wizard-tuteur-suggestion" data-suggestion-index="${index}">
                    <b>${escapeHTML(tuteur.nom_prenom)}</b>
                    <span>${escapeHTML(tuteur.telephone || '')}${tuteur.email ? ' · ' + escapeHTML(tuteur.email) : ''}</span>
                </button>
            `
            )
            .join('');

        suggestionsBox.querySelectorAll('[data-suggestion-index]').forEach((btn) => {
            btn.addEventListener('click', () => selectTuteur(tuteurs[Number(btn.dataset.suggestionIndex)]));
        });
    }

    nomPrenomInput.addEventListener('input', () => {
        clearMatch();
        clearTimeout(debounceTimer);
        const recherche = nomPrenomInput.value.trim();

        if (recherche.length < 2) {
            clearSuggestions();

            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`${rechercheUrl}?q=${encodeURIComponent(recherche)}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((tuteurs) => renderSuggestions(tuteurs))
                .catch(() => {});
        }, DEBOUNCE_MS);
    });

    return {
        getMatch: () => matched,
        reset: () => {
            clearMatch();
            clearSuggestions();
        },
    };
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
