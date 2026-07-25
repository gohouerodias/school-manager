/**
 * Wires up every [data-password-toggle] button to show/hide the password
 * input whose id matches its value. Used on the login, first-login and
 * future password-related forms.
 */
export function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);

            if (!input) {
                return;
            }

            const willBeVisible = input.type === 'password';
            input.type = willBeVisible ? 'text' : 'password';
            button.classList.toggle('is-visible', willBeVisible);
        });
    });
}
