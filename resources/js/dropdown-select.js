/**
 * Replaces every select.role-select / select.filter-select's native
 * rendering with a custom animated dropdown (trigger button + floating
 * option list), while keeping the real <select> in the DOM as the actual
 * source of truth — so validation (form-required.js), form submission,
 * live search (live-search.js's FormData serialization), and anything
 * else reading/writing `.value` keep working completely unchanged.
 *
 * The menu is positioned with `position: fixed` from the trigger's
 * bounding rect (same technique as action-menu.js's row-actions dropdown)
 * rather than plain CSS `position: absolute`, since several of these
 * selects live inside a scrollable .panel-body that would otherwise clip
 * an absolutely positioned menu.
 *
 * The menu is also appended directly to <body> instead of staying nested
 * inside its .dropdown-select wrapper. This matters because every form
 * that isn't the toolbar's filter row lives inside an <x-slide-panel>,
 * and .panel slides in/out via `transform` — any element with a `transform`
 * (any value, including translateX(0) while "open") becomes the containing
 * block for its `position: fixed` descendants instead of the viewport. Left
 * nested, the menu's fixed top/left (computed from viewport coordinates)
 * would be resolved against the panel's box instead, landing it far off
 * to the side — which is exactly why dropdowns inside the "Nouvel
 * apprenant"/"Modifier la fiche" panels appeared to show no options at
 * all. Moving the menu to <body> keeps its containing block as the true
 * viewport regardless of any ancestor's transform.
 *
 * Reusable: any current or future <select class="role-select"> or
 * <select class="filter-select"> is enhanced automatically — no per-form
 * wiring needed, anywhere in the app.
 *
 * Elements swapped in later by live search (live-search.js replaces
 * #eleves-table-region's innerHTML on every keystroke) are NOT picked up by
 * this initial querySelectorAll, since it only runs once at DOMContentLoaded
 * and doesn't observe future DOM mutations. Any select-bearing region that
 * gets replaced by innerHTML assignment (like the éditable "Classe" column,
 * see eleve-classe-assign.js) must call enhanceDropdownSelectsIn() on the
 * new content afterwards — see live-search.js's runSearch().
 */
export function initDropdownSelects() {
    enhanceDropdownSelectsIn(document);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.dropdown-select') && !event.target.closest('.dropdown-select-menu')) {
            closeAll();
        }
    });
    // Scrolling the page/panel behind an open menu closes it (its fixed
    // position would otherwise go stale relative to the trigger). But
    // scrolling *inside* the menu itself — its own option list has
    // `overflow-y: auto` for long lists like "Classe désirée" — is a
    // normal scroll event too (captured here since it doesn't bubble to
    // window on its own), so that case is explicitly excluded.
    window.addEventListener('scroll', (event) => {
        if (event.target?.closest?.('.dropdown-select-menu')) {
            return;
        }
        closeAll();
    }, true);
    window.addEventListener('resize', closeAll);
}

/**
 * Enhances every not-yet-enhanced select.role-select/.filter-select found
 * within `container` (defaults to the whole document). enhanceSelect()
 * already no-ops on selects that are already wrapped, so this is safe to
 * call repeatedly on the same container (e.g. once per live-search swap).
 */
export function enhanceDropdownSelectsIn(container = document) {
    container.querySelectorAll('select.role-select, select.filter-select').forEach(enhanceSelect);
}

/**
 * Re-syncs a single enhanced select's trigger label + selected-option
 * highlighting with its current `.value`. Setting `select.value = ...`
 * from JS (e.g. prefilling an edit panel from a clicked row's data-*
 * attributes) does not fire a `change` event on its own, so callers that
 * do this for a select.role-select/.filter-select should call this right
 * after — see eleve-edit.js / eleve-tuteur-document.js.
 */
export function refreshDropdownSelect(select) {
    const wrap = select?.closest('.dropdown-select');
    if (wrap) {
        syncTrigger(wrap);
    }
}

function enhanceSelect(select) {
    if (select.closest('.dropdown-select')) {
        return; // already enhanced
    }

    const wrap = document.createElement('div');
    wrap.className = select.classList.contains('role-select') ? 'dropdown-select block' : 'dropdown-select';

    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'dropdown-select-trigger';
    trigger.disabled = select.disabled;
    trigger.innerHTML = `
        <span class="dropdown-select-label"></span>
        <svg class="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
    `;
    wrap.appendChild(trigger);

    const menu = document.createElement('div');
    menu.className = 'dropdown-select-menu';
    menu.setAttribute('role', 'listbox');
    // See the module docblock: appended to <body> (not `wrap`) so its
    // `position: fixed` is always relative to the real viewport, even
    // when this select lives inside a slide-panel (which uses `transform`
    // for its open/close animation).
    document.body.appendChild(menu);
    menu.__wrap = wrap;
    menu.__trigger = trigger;
    wrap.__menu = menu;

    buildOptions(select, menu);
    syncTrigger(wrap);

    trigger.addEventListener('click', () => {
        const isOpen = menu.classList.contains('open');
        closeAll();
        if (!isOpen) {
            openMenu(wrap, trigger, menu);
        }
    });

    trigger.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'ArrowUp', 'Enter', ' ', 'Escape'].includes(event.key)) {
            return;
        }
        event.preventDefault();

        if (event.key === 'Escape') {
            closeAll();
            return;
        }

        if (!menu.classList.contains('open')) {
            openMenu(wrap, trigger, menu);
            return;
        }

        if (event.key === 'ArrowDown') {
            moveHighlight(menu, 1);
        } else if (event.key === 'ArrowUp') {
            moveHighlight(menu, -1);
        } else {
            menu.querySelector('.dropdown-select-option.highlighted')?.click();
        }
    });

    // Keeps the trigger in sync if the select's value is ever changed by
    // something that *does* dispatch a real `change` event.
    select.addEventListener('change', () => syncTrigger(wrap));
}

function buildOptions(select, menu) {
    menu.innerHTML = '';

    Array.from(select.options).forEach((option) => {
        const item = document.createElement('div');
        item.className = option.value === '' ? 'dropdown-select-option placeholder' : 'dropdown-select-option';
        item.setAttribute('role', 'option');
        item.dataset.value = option.value;
        item.innerHTML = `
            <span>${escapeHTML(option.textContent.trim())}</span>
            <span class="option-check">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 13 4 4L19 7"/></svg>
            </span>
        `;

        item.addEventListener('click', () => {
            select.value = option.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closeAll();
            menu.__trigger?.focus();
        });

        menu.appendChild(item);
    });
}

function syncTrigger(wrap) {
    const select = wrap.querySelector('select');
    const trigger = wrap.querySelector('.dropdown-select-trigger');
    const label = trigger?.querySelector('.dropdown-select-label');
    const selectedOption = select.options[select.selectedIndex];

    if (label) {
        label.textContent = selectedOption ? selectedOption.textContent.trim() : '';
    }

    wrap.__menu?.querySelectorAll('.dropdown-select-option').forEach((item) => {
        item.classList.toggle('selected', item.dataset.value === select.value);
    });
}

function openMenu(wrap, trigger, menu) {
    wrap.classList.add('open');
    menu.classList.add('open');

    const rect = trigger.getBoundingClientRect();
    const menuHeight = menu.offsetHeight;

    let top = rect.bottom + 6;
    if (top + menuHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - menuHeight - 6);
    }

    menu.style.top = `${top}px`;
    menu.style.left = `${rect.left}px`;
    menu.style.width = `${rect.width}px`;

    const highlighted = menu.querySelector('.dropdown-select-option.selected') || menu.querySelector('.dropdown-select-option');
    setHighlight(menu, highlighted);
}

function closeAll() {
    document.querySelectorAll('.dropdown-select.open').forEach((wrap) => wrap.classList.remove('open'));
    document.querySelectorAll('.dropdown-select-menu.open').forEach((menu) => menu.classList.remove('open'));
}

function moveHighlight(menu, delta) {
    const items = Array.from(menu.querySelectorAll('.dropdown-select-option'));
    if (!items.length) {
        return;
    }

    const currentIndex = items.findIndex((item) => item.classList.contains('highlighted'));
    const nextIndex = (currentIndex + delta + items.length) % items.length;
    setHighlight(menu, items[nextIndex]);
    items[nextIndex].scrollIntoView({ block: 'nearest' });
}

function setHighlight(menu, item) {
    menu.querySelectorAll('.dropdown-select-option.highlighted').forEach((el) => el.classList.remove('highlighted'));
    item?.classList.add('highlighted');
}

function escapeHTML(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}
