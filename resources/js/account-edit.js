/**
 * Populates the single shared "edit-user" slide-panel with the clicked
 * row's data (nom/prénoms/telephone/email) and points its form at that
 * user's update route. Pairs with data-edit-user-trigger buttons rendered
 * per row in comptes/index.blade.php; the panel's own open/close is handled
 * generically by initSlidePanels() (data-panel-open="edit-user").
 *
 * The `users.name` column stays a single field, so Nom/Prénoms are only a
 * split editing UI: they're recombined into the hidden #edit-user-name
 * input (submitted as `name`) on every change. When prefilling from an
 * existing user, we can't know the original split, so we fall back to
 * "last word = nom, everything else = prénoms" as a reasonable default.
 */
export function initAccountEdit() {
    const form = document.getElementById('edit-user-form');

    if (!form) {
        return;
    }

    const nomInput = document.getElementById('edit-user-nom');
    const prenomsInput = document.getElementById('edit-user-prenoms');
    const nameInput = document.getElementById('edit-user-name');
    const telephoneInput = document.getElementById('edit-user-telephone');
    const emailInput = document.getElementById('edit-user-email');
    const editUrlHidden = document.getElementById('edit-user-edit-url');

    const syncName = () => {
        nameInput.value = `${prenomsInput.value.trim()} ${nomInput.value.trim()}`.trim();
    };

    const splitFullNameInto = (fullName) => {
        const words = fullName.trim().split(/\s+/).filter(Boolean);
        nomInput.value = words.length ? words[words.length - 1] : '';
        prenomsInput.value = words.slice(0, -1).join(' ');
    };

    nomInput.addEventListener('input', syncName);
    prenomsInput.addEventListener('input', syncName);

    // A validation error on this form flashes `name` (see old('name') in the
    // hidden #edit-user-name input) and reopens this panel without a click
    // (panel-error-reopen.js) — split it back into nom/prénoms so the user
    // sees what they typed rather than blank fields.
    if (nameInput.value && !nomInput.value && !prenomsInput.value) {
        splitFullNameInto(nameInput.value);
    }

    document.querySelectorAll('[data-edit-user-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            if (editUrlHidden) {
                editUrlHidden.value = trigger.dataset.editUrl;
            }
            telephoneInput.value = trigger.dataset.editTelephone ?? '';
            emailInput.value = trigger.dataset.editEmail ?? '';

            const fullName = (trigger.dataset.editName ?? '').trim();
            splitFullNameInto(fullName);
            nameInput.value = fullName;
        });
    });
}
