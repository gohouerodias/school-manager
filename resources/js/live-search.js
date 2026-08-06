import { enhanceDropdownSelectsIn } from './dropdown-select';
import { markClasseAssignSelects } from './eleve-classe-assign';
import { markStatutAssignSelects } from './eleve-statut-assign';

/**
 * Generic debounced live search for any [data-live-search] input inside a
 * <form>: on each keystroke, the enclosing form is serialized (so other
 * filters already selected — classe, statut, date de création — travel
 * along with the search term) and re-submitted via fetch instead of a full
 * page reload. The response HTML replaces the element whose id matches
 * data-live-search-target.
 *
 * The server tells the two cases apart via the X-Requested-With header set
 * below (see EleveController::index()'s `$request->ajax()` branch, which
 * returns just the table partial instead of the full page).
 *
 * A sibling [data-live-search-spinner] element (rendered by
 * <x-toolbar-search>) is shown while a request is in flight. If the user
 * types again before a request resolves, the in-flight one is aborted so
 * a slow, now-stale response can't overwrite a newer one.
 *
 * Any [data-export-link] on the page (the "Excel"/"PDF" buttons, see
 * <x-export-buttons>) has its href refreshed to the same query string on
 * every search, so exporting always matches what's currently on screen.
 */
const DEBOUNCE_MS = 350;

export function initLiveSearch() {
    document.querySelectorAll('[data-live-search]').forEach((input) => {
        const url = input.dataset.liveSearchUrl;
        const target = document.getElementById(input.dataset.liveSearchTarget);
        const form = input.closest('form');

        if (!url || !target || !form) {
            return;
        }

        const spinner = input.parentElement?.querySelector('[data-live-search-spinner]');
        let debounceTimer = null;
        let abortController = null;

        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                abortController?.abort();
                abortController = new AbortController();
                runSearch(url, form, target, spinner, abortController.signal);
            }, DEBOUNCE_MS);
        });
    });
}

function runSearch(url, form, target, spinner, signal) {
    const params = new URLSearchParams(new FormData(form));
    const requestUrl = `${url}?${params.toString()}`;

    document.querySelectorAll('[data-export-link]').forEach((link) => {
        const base = link.dataset.exportBaseUrl;
        if (base) {
            link.href = `${base}?${params.toString()}`;
        }
    });

    spinner?.classList.add('show');

    fetch(requestUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal,
    })
        .then((response) => response.text())
        .then((html) => {
            target.innerHTML = html;
            window.history.replaceState(null, '', requestUrl);
            // The swapped-in HTML may contain <select>s (e.g. the éditable
            // "Classe" column) that this one-time-at-load enhancement /
            // baseline-tracking hasn't seen yet.
            enhanceDropdownSelectsIn(target);
            markClasseAssignSelects(target);
            markStatutAssignSelects(target);
        })
        .catch((error) => {
            // AbortError just means a newer keystroke superseded this
            // request — nothing to show the user. Any other failure simply
            // leaves the list as it was; they can retry by typing again.
        })
        .finally(() => {
            if (!signal.aborted) {
                spinner?.classList.remove('show');
            }
        });
}
