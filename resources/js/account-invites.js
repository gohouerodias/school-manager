/**
 * Page-specific logic for the "Inviter un utilisateur" panel on the account
 * management screen: lets an admin queue several email+profil pairs before
 * submitting them all in a single request (one account each, server-side).
 */
const roleDescriptions = {
    administrateur: {
        title: 'Administrateur',
        text: 'Accès complet : gestion des comptes, des classes, des documents et de tous les dossiers élèves.',
    },
    agent_scolarite: {
        title: 'Agent de scolarité',
        text: "Gère les fiches élèves, les documents, les inscriptions et génère les bulletins. Pas d'accès à la gestion des comptes.",
    },
    enseignant: {
        title: 'Enseignant',
        text: 'Saisit les notes et les observations pédagogiques, uniquement pour les classes qui lui sont assignées.',
    },
    direction: {
        title: 'Direction',
        text: "Consultation des dossiers, accès aux rapports et statistiques de l'établissement.",
    },
};

/**
 * Same `.field.invalid` + `<p class="error">` pattern as the app-wide
 * `[required]` styling (see resources/js/form-required.js), applied here by
 * hand: these fields deliberately have no `required` attribute, since they
 * feed a "pending list" the user builds up before a single real submit (see
 * the submit listener below), so the generic mechanism — which validates on
 * submit — would incorrectly block that submit once fields are cleared back
 * out after being queued.
 */
function setFieldInvalid(input, invalid, message = '') {
    input.closest('.field')?.classList.toggle('invalid', invalid);

    const errorEl = document.querySelector(`[data-error-for="${input.id}"]`);
    if (errorEl) {
        errorEl.textContent = invalid ? message : '';
        errorEl.style.display = invalid ? 'block' : 'none';
    }
}

export function initAccountInvites() {
    const form = document.getElementById('invite-form');

    if (!form) {
        return;
    }

    const emailInput = document.getElementById('invite-email');
    const nomInput = document.getElementById('invite-nom');
    const prenomsInput = document.getElementById('invite-prenoms');
    const telephoneInput = document.getElementById('invite-telephone');
    const roleSelect = document.getElementById('invite-role');
    const roleTitle = document.getElementById('invite-role-desc-title');
    const roleText = document.getElementById('invite-role-desc-text');
    const addButton = document.getElementById('invite-add-btn');
    const section = document.getElementById('invite-pending-section');
    const list = document.getElementById('invite-pending-list');
    const count = document.getElementById('invite-pending-count');
    const saveButton = document.getElementById('invite-save-btn');
    const callout = document.getElementById('invite-callout');
    const addError = document.getElementById('invite-add-error');

    const roleLabels = {};
    Array.from(roleSelect.options).forEach((option) => {
        roleLabels[option.value] = option.textContent.trim();
    });

    let pending = [];

    function updateRoleDescription() {
        const info = roleDescriptions[roleSelect.value];
        if (!info) {
            return;
        }
        roleTitle.textContent = info.title;
        roleText.textContent = info.text;
    }

    function render() {
        count.textContent = String(pending.length);
        section.style.display = pending.length ? 'block' : 'none';
        saveButton.disabled = pending.length === 0;
        callout.style.display = pending.length ? 'block' : 'none';

        list.innerHTML = pending
            .map(
                (invite, index) => `
                <div class="pending-item">
                    <div><span class="pmail">${invite.name} — ${invite.email}</span><br><span class="prole">${roleLabels[invite.profil] ?? invite.profil}</span></div>
                    <button type="button" class="premove" data-remove-index="${index}">✕</button>
                </div>`
            )
            .join('');

        list.querySelectorAll('[data-remove-index]').forEach((button) => {
            button.addEventListener('click', () => {
                pending.splice(Number(button.dataset.removeIndex), 1);
                render();
            });
        });
    }

    roleSelect.addEventListener('change', updateRoleDescription);
    updateRoleDescription();

    const invitableInputs = [emailInput, nomInput, prenomsInput, telephoneInput];
    invitableInputs.forEach((input) => {
        input.addEventListener('input', () => {
            setFieldInvalid(input, false);
            if (invitableInputs.every((i) => !i.closest('.field')?.classList.contains('invalid'))) {
                addError.style.display = 'none';
            }
        });
    });

    addButton.addEventListener('click', () => {
        const email = emailInput.value.trim();
        const nom = nomInput.value.trim();
        const prenoms = prenomsInput.value.trim();
        const telephone = telephoneInput.value.trim();
        const name = `${prenoms} ${nom}`.trim();

        let hasError = false;
        [
            [emailInput, email && email.includes('@'), 'Adresse e-mail invalide.'],
            [nomInput, nom.length > 0, 'Le nom est obligatoire.'],
            [prenomsInput, prenoms.length > 0, 'Les prénoms sont obligatoires.'],
            [telephoneInput, telephone.length > 0, 'Le numéro de téléphone est obligatoire.'],
        ].forEach(([input, isValid, message]) => {
            setFieldInvalid(input, !isValid, message);
            hasError = hasError || !isValid;
        });

        if (hasError) {
            addError.textContent = 'Merci de renseigner tous les champs obligatoires (marqués *) avant d’ajouter cet utilisateur à la liste.';
            addError.style.display = 'block';
            return;
        }

        addError.style.display = 'none';
        pending.push({ email, name, telephone, profil: roleSelect.value });
        render();
        emailInput.value = '';
        nomInput.value = '';
        prenomsInput.value = '';
        telephoneInput.value = '';
    });

    form.addEventListener('submit', (event) => {
        if (pending.length === 0) {
            event.preventDefault();
            return;
        }

        form.querySelectorAll('[data-generated-invite]').forEach((el) => el.remove());

        pending.forEach((invite, index) => {
            [
                ['email', invite.email],
                ['name', invite.name],
                ['telephone', invite.telephone],
                ['profil', invite.profil],
            ].forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `invites[${index}][${key}]`;
                input.value = value;
                input.dataset.generatedInvite = 'true';
                form.appendChild(input);
            });
        });
    });
}
