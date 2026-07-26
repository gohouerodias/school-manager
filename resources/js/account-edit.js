/**
 * Populates the single shared "edit-user" slide-panel with the clicked
 * row's data (name/telephone/email) and points its form at that user's
 * update route. Pairs with data-edit-user-trigger buttons rendered per row
 * in comptes/index.blade.php; the panel's own open/close is handled
 * generically by initSlidePanels() (data-panel-open="edit-user").
 */
export function initAccountEdit() {
    const form = document.getElementById('edit-user-form');

    if (!form) {
        return;
    }

    const nameInput = document.getElementById('edit-user-name');
    const telephoneInput = document.getElementById('edit-user-telephone');
    const emailInput = document.getElementById('edit-user-email');

    document.querySelectorAll('[data-edit-user-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            form.action = trigger.dataset.editUrl;
            nameInput.value = trigger.dataset.editName ?? '';
            telephoneInput.value = trigger.dataset.editTelephone ?? '';
            emailInput.value = trigger.dataset.editEmail ?? '';
        });
    });
}
