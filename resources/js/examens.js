/**
 * "Créer un examen" panel (resources/views/academique/examens/index.blade.php):
 * only the systèmes disponibles (maternelle, primaire — see
 * App\Enums\SystemeScolaire::estDisponible()) ask for a date d'examen /
 * date limite de saisie — choisir secondaire hides those fields (and drops
 * their `required`, since the backend doesn't need them for that branch —
 * see StoreExamenRequest's `required_if:systeme,maternelle,primaire`) and
 * shows a hint that the système secondaire is unavailable for now.
 */
const SYSTEMES_INDISPONIBLES = ['secondaire'];

export function initExamens() {
    initNewExamenFields();
    initExamenEdit();
}

function initNewExamenFields() {
    const systemeSelect = document.getElementById('new-examen-systeme');
    const primaireFields = document.getElementById('new-examen-primaire-fields');
    const secondaireHint = document.getElementById('new-examen-secondaire-hint');
    const dateExamen = document.getElementById('new-examen-date');
    const dateLimite = document.getElementById('new-examen-date-limite');

    if (!systemeSelect || !primaireFields || !secondaireHint) {
        return;
    }

    function syncFields() {
        const isDisponible = systemeSelect.value !== '' && !SYSTEMES_INDISPONIBLES.includes(systemeSelect.value);

        primaireFields.style.display = isDisponible ? 'block' : 'none';
        secondaireHint.style.display = isDisponible ? 'none' : 'block';

        [dateExamen, dateLimite].forEach((input) => {
            if (!input) {
                return;
            }
            input.required = isDisponible;
        });
    }

    systemeSelect.addEventListener('change', syncFields);
    syncFields();
}

/**
 * "Modifier l'examen" panel — same data-edit-*-trigger pattern as
 * academique-settings.js's initNiveauEdit(): the row's ✎ button carries the
 * examen's current values as data attributes, this just copies them into
 * the shared edit form when clicked.
 */
function initExamenEdit() {
    const form = document.getElementById('edit-examen-form');
    if (!form) {
        return;
    }

    const systemeAnnee = document.getElementById('edit-examen-systeme-annee');
    const dateInput = document.getElementById('edit-examen-date');
    const dateLimiteInput = document.getElementById('edit-examen-date-limite');
    const editUrlHidden = document.getElementById('edit-examen-edit-url');

    document.querySelectorAll('[data-edit-examen-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }

            systemeAnnee.textContent = `${trigger.dataset.editSysteme} — ${trigger.dataset.editAnnee}`;

            [dateInput, dateLimiteInput].forEach((input) => {
                input.min = trigger.dataset.editMin ?? '';
                input.max = trigger.dataset.editMax ?? '';
            });

            dateInput.value = trigger.dataset.editDateExamen ?? '';
            dateLimiteInput.value = trigger.dataset.editDateLimite ?? '';
        });
    });
}
