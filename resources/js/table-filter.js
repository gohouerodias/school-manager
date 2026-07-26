/**
 * Generic client-side search/filter for any <x-data-table>.
 *
 * Contract:
 * - table:   <table id="TABLE_ID">
 * - rows:    <tr data-row data-search="name email" data-profil="..." data-statut="...">
 * - search:  <input data-table-filter-for="TABLE_ID"> (type="search"/"text")
 * - filters: <select data-table-filter-for="TABLE_ID" data-filter-key="profil">
 * - empty state (optional): [data-table-empty-for="TABLE_ID"]
 *
 * Any future table (élèves, classes...) can reuse this by following the
 * same data attributes — no new JS required.
 */
export function initTableFilters() {
    document.querySelectorAll('[data-table-filter-for]').forEach((control) => {
        const tableId = control.dataset.tableFilterFor;
        control.addEventListener('input', () => applyFilters(tableId));
        control.addEventListener('change', () => applyFilters(tableId));
    });
}

function applyFilters(tableId) {
    const table = document.getElementById(tableId);
    if (!table) {
        return;
    }

    const controls = document.querySelectorAll(`[data-table-filter-for="${tableId}"]`);
    const rows = table.querySelectorAll('tbody tr[data-row]');
    let visibleCount = 0;

    rows.forEach((row) => {
        let matches = true;

        controls.forEach((control) => {
            if (!matches) {
                return;
            }

            const value = control.value.trim().toLowerCase();
            if (!value) {
                return;
            }

            if (control.type === 'search' || control.type === 'text') {
                const haystack = (row.dataset.search || '').toLowerCase();
                if (!haystack.includes(value)) {
                    matches = false;
                }
            } else if (control.dataset.filterKey) {
                const rowValue = row.dataset[control.dataset.filterKey] || '';
                if (rowValue !== control.value) {
                    matches = false;
                }
            }
        });

        row.style.display = matches ? '' : 'none';
        if (matches) {
            visibleCount++;
        }
    });

    const emptyState = document.querySelector(`[data-table-empty-for="${tableId}"]`);
    if (emptyState) {
        emptyState.style.display = visibleCount === 0 ? '' : 'none';
    }
}
