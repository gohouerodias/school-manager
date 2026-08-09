/**
 * "Affecter un enseignant" panel on the année académique setup page
 * (resources/views/academique/annees/show.blade.php): the "Matière" select
 * starts with every matière listed, but only those actually in the chosen
 * classe's programme (see Classe::matieres(), the `classe_matiere` pivot —
 * itself inherited from the niveau's programme at classe creation, see
 * Academique\ClasseController::store()) should be pickable. Each classe
 * <option> carries its matière ids in `data-matiere-ids` (comma-separated),
 * set server-side; this just shows/hides + disables the matière <option>s
 * to match whenever the classe changes.
 */
export function initAnneeAcademiqueShow() {
    const classeSelect = document.getElementById('new-affectation-classe');
    const matiereSelect = document.getElementById('new-affectation-matiere');

    if (!classeSelect || !matiereSelect) {
        return;
    }

    const matiereOptions = Array.from(matiereSelect.options).filter((option) => option.value !== '');
    const placeholder = matiereSelect.options[0];

    function syncMatieres() {
        const selected = classeSelect.options[classeSelect.selectedIndex];
        const matiereIds = (selected?.dataset.matiereIds ?? '').split(',').filter(Boolean);

        if (!classeSelect.value) {
            placeholder.textContent = "— Sélectionnez d'abord une classe —";
            matiereOptions.forEach((option) => {
                option.hidden = true;
                option.disabled = true;
            });
            matiereSelect.value = '';
            return;
        }

        placeholder.textContent = matiereIds.length ? '— Sélectionner —' : 'Aucune matière au programme de cette classe';

        matiereOptions.forEach((option) => {
            const inProgramme = matiereIds.includes(option.value);
            option.hidden = !inProgramme;
            option.disabled = !inProgramme;
        });

        if (matiereSelect.value && !matiereIds.includes(matiereSelect.value)) {
            matiereSelect.value = '';
        }
    }

    classeSelect.addEventListener('change', syncMatieres);
    syncMatieres();
}
