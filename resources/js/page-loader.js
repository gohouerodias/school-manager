/**
 * Big, branded full-page loading overlay (#page-loader, see layouts/app.blade.php)
 * shown while the browser is navigating to a new page. This is a classic
 * server-rendered Laravel app (no SPA router), so "the page is loading" is a
 * real, full browser navigation — the overlay's markup + critical CSS are
 * inlined straight in the <head>, before @vite's bundle even loads, so it
 * paints on the very first frame of every page instead of a blank flash.
 *
 * initPageLoader() does two things:
 *   1. Fades the overlay out once *this* page is ready (with a minimum
 *      display time, so it doesn't just flicker on fast loads).
 *   2. Re-shows it the moment the user clicks a normal navigation link or
 *      submits a normal form, so the very next page also starts covered
 *      instead of showing a blank tab while the server responds.
 *
 * showPageLoader() is exported separately for resources/js/confirm-submit-form.js,
 * which submits its form programmatically *after* a confirmation step (so it
 * can't rely on this module's own submit listener timing).
 */
const MIN_VISIBLE_MS = 350;
const pageLoadStartedAt = Date.now();

export function initPageLoader() {
    const loader = document.getElementById('page-loader');

    if (!loader) {
        return;
    }

    const elapsed = Date.now() - pageLoadStartedAt;
    setTimeout(() => loader.classList.add('hide'), Math.max(0, MIN_VISIBLE_MS - elapsed));

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a[href]');
        if (link && isRealNavigationLink(link)) {
            showPageLoader();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (event.defaultPrevented || form.matches('[data-confirm-submit]') || form.hasAttribute('data-no-loader')) {
            // data-confirm-submit forms (confirm-submit-form.js) show the
            // loader themselves, only once the user actually confirms.
            return;
        }

        showPageLoader();
    });
}

export function showPageLoader() {
    document.getElementById('page-loader')?.classList.remove('hide');
}

/**
 * True for plain links that actually leave the page — excludes in-page
 * anchors, new-tab/download links, and anything already wired to its own
 * JS behaviour (opening a panel/modal, switching a fiche tab, etc.), which
 * shouldn't get the full-page loader.
 */
function isRealNavigationLink(link) {
    const href = link.getAttribute('href');

    if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
        return false;
    }

    if (link.target === '_blank' || link.hasAttribute('download')) {
        return false;
    }

    const jsHooks = ['panelOpen', 'panelClose', 'ficheTrigger', 'ficheTab', 'editTuteurTrigger', 'editUserTrigger', 'noLoader'];

    return ! jsHooks.some((hook) => hook in link.dataset);
}
