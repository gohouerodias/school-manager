/**
 * Two small, generic UX enhancements applied to every `[required]` field
 * across the app, without touching each individual form:
 *
 *  - A red asterisk appended to the field's <label> (added once, so a
 *    group of required radios sharing one label — e.g. the "Type" choice
 *    cards in parametres.blade.php — only gets a single "*").
 *  - The field (and its wrapping .field, so the label turns red too) is
 *    highlighted as soon as a submit attempt finds it empty, cleared again
 *    as soon as the user fixes it.
 *
 * This runs its own check via `checkValidity()` on submit rather than
 * relying on the browser's automatic constraint validation (the native
 * `invalid` event), because a few auth forms use `novalidate` to keep
 * their existing server-driven @error flow — this way the same red
 * highlight works consistently everywhere regardless of that attribute.
 * `preventDefault()` only fires when a required field is genuinely empty,
 * so it composes fine with other submit listeners on the same form (e.g.
 * eleve-tuteur-document.js's file-required check).
 */
export function initRequiredFieldStyling() {
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(addRequiredStar);

    const forms = new Set(Array.from(requiredFields).map((field) => field.form).filter(Boolean));

    forms.forEach((form) => {
        const fieldsInForm = Array.from(requiredFields).filter((field) => field.form === form);

        form.addEventListener('submit', (event) => {
            let firstInvalid = null;

            fieldsInForm.forEach((field) => {
                if (field.checkValidity()) {
                    setInvalid(field, false);
                } else {
                    setInvalid(field, true);
                    firstInvalid ??= field;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });

        fieldsInForm.forEach((field) => {
            const clearIfValid = () => {
                if (field.checkValidity()) {
                    setInvalid(field, false);
                }
            };
            field.addEventListener('input', clearIfValid);
            field.addEventListener('change', clearIfValid);
        });
    });
}

function addRequiredStar(field) {
    const label = field.id
        ? document.querySelector(`label[for="${field.id}"]`)
        : field.closest('.field')?.querySelector('label');

    if (label && !label.querySelector('.required-star')) {
        const star = document.createElement('span');
        star.className = 'required-star';
        star.textContent = ' *';
        label.appendChild(star);
    }
}

function setInvalid(field, invalid) {
    field.closest('.field')?.classList.toggle('invalid', invalid);
}
