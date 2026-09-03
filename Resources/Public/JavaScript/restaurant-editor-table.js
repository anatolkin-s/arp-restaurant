(() => {
    'use strict';

    const SELECTOR_GRID = '[data-arp-editor-grid]';

    function controlValue(cell) {
        const controls = cell.querySelectorAll('input, textarea, select');
        if (!controls.length) {
            return null;
        }
        const parts = [];
        controls.forEach((control) => {
            if (control.type === 'hidden') {
                return;
            }
            parts.push(control.value || '');
        });
        return parts.join(' ');
    }

    function cellSearchText(cell) {
        const fromControl = controlValue(cell);
        if (fromControl !== null) {
            return fromControl;
        }
        return cell.innerText || '';
    }

    function searchText(row) {
        const parts = [];
        row.querySelectorAll('[data-arp-col]').forEach((cell) => {
            parts.push(cellSearchText(cell));
        });
        return parts.join(' ').toLowerCase();
    }

    function numberValue(row, name) {
        const raw = row.getAttribute(name);
        if (raw === null || raw === '') {
            return null;
        }
        const value = Number(raw);
        return Number.isFinite(value) ? value : null;
    }

    /**
     * View-only numeric key matching DecimalMinorUnitParser (2 fraction digits).
     * Invalid or empty prices sort last. Not used as submitted amountMinor.
     */
    function parsePriceSortValue(raw) {
        const value = String(raw || '').trim();
        if (value === '' || value.charAt(0) === '-') {
            return null;
        }
        if (!/^\d+(\.\d+)?$/.test(value)) {
            return null;
        }
        const parts = value.split('.');
        const whole = parts[0];
        const fraction = parts[1] || '';
        if (fraction.length > 2 || whole.length > 9) {
            return null;
        }
        const padded = (fraction + '00').slice(0, 2);
        return Number(whole) * 100 + Number(padded);
    }

    function priceSortValue(row) {
        const cell = row.querySelector('[data-arp-col="price"]');
        const fromControl = cell ? controlValue(cell) : null;
        if (fromControl !== null) {
            return parsePriceSortValue(fromControl);
        }
        return numberValue(row, 'data-arp-price');
    }

    function textValue(row, column) {
        const cell = row.querySelector('[data-arp-col="' + column + '"]');
        if (!cell) {
            return '';
        }
        const fromControl = controlValue(cell);
        if (fromControl !== null) {
            return fromControl.trim();
        }
        const explicit = cell.getAttribute('data-arp-sort-value');
        if (explicit !== null) {
            return explicit.trim();
        }
        return (cell.innerText || '').trim();
    }

    function compareRows(a, b, key, type, direction) {
        let result = 0;
        if (type === 'number') {
            const left = key === 'price' ? priceSortValue(a) : numberValue(a, 'data-arp-line');
            const right = key === 'price' ? priceSortValue(b) : numberValue(b, 'data-arp-line');
            const leftEmpty = left === null;
            const rightEmpty = right === null;
            if (leftEmpty && rightEmpty) {
                result = 0;
            } else if (leftEmpty) {
                return 1;
            } else if (rightEmpty) {
                return -1;
            } else {
                result = left - right;
            }
        } else {
            result = textValue(a, key).localeCompare(textValue(b, key), undefined, {
                sensitivity: 'base',
                numeric: true,
            });
        }
        if (result === 0) {
            return Number(a.getAttribute('data-arp-order')) - Number(b.getAttribute('data-arp-order'));
        }
        return direction === 'desc' ? -result : result;
    }

    function bindGrid(root) {
        const table = root.querySelector('[data-arp-editor-table]');
        const tbody = table ? table.tBodies[0] : null;
        if (!table || !tbody) {
            return;
        }

        const rows = Array.prototype.slice.call(tbody.rows);
        rows.forEach((row, index) => {
            if (!row.hasAttribute('data-arp-order')) {
                row.setAttribute('data-arp-order', String(index));
            }
        });

        const search = root.querySelector('[data-arp-editor-search]');
        const reset = root.querySelector('[data-arp-editor-reset]');
        const empty = root.querySelector('[data-arp-editor-empty]');
        const labelAscNode = root.querySelector('[data-arp-label-asc]');
        const labelDescNode = root.querySelector('[data-arp-label-desc]');
        const labelAsc = ((labelAscNode && labelAscNode.textContent) || 'ascending').trim();
        const labelDesc = ((labelDescNode && labelDescNode.textContent) || 'descending').trim();

        let sortKey = '';
        let sortDirection = 'asc';

        function headers() {
            return table.querySelectorAll('thead th[aria-sort]');
        }

        function updateSortUi() {
            headers().forEach((header) => {
                const button = header.querySelector('[data-arp-sort]');
                const state = header.querySelector('[data-arp-sort-state]');
                const key = button ? button.getAttribute('data-arp-sort') : '';
                if (sortKey === key) {
                    header.setAttribute('aria-sort', sortDirection === 'desc' ? 'descending' : 'ascending');
                    if (state) {
                        state.textContent = sortDirection === 'desc' ? labelDesc : labelAsc;
                    }
                } else {
                    header.setAttribute('aria-sort', 'none');
                    if (state) {
                        state.textContent = '';
                    }
                }
            });
        }

        function filterRows() {
            const query = search ? search.value.trim().toLowerCase() : '';
            let visible = 0;
            Array.prototype.forEach.call(tbody.rows, (row) => {
                const match = query === '' || searchText(row).indexOf(query) !== -1;
                row.hidden = !match;
                if (match) {
                    visible += 1;
                }
            });
            if (empty) {
                empty.hidden = visible > 0 || query === '';
            }
        }

        function sortRows() {
            const current = Array.prototype.slice.call(tbody.rows);
            if (sortKey === '') {
                current.sort((a, b) => Number(a.getAttribute('data-arp-order')) - Number(b.getAttribute('data-arp-order')));
            } else {
                const button = table.querySelector('[data-arp-sort="' + sortKey + '"]');
                const type = button ? button.getAttribute('data-arp-sort-type') : 'text';
                current.sort((a, b) => compareRows(a, b, sortKey, type, sortDirection));
            }
            current.forEach((row) => {
                tbody.appendChild(row);
            });
        }

        function renumber() {
            let index = 0;
            Array.prototype.forEach.call(tbody.rows, (row) => {
                if (row.hidden) {
                    return;
                }
                index += 1;
                const cell = row.querySelector('[data-arp-row-index]');
                if (cell) {
                    cell.textContent = String(index);
                }
            });
        }

        function refresh() {
            filterRows();
            sortRows();
            renumber();
            updateSortUi();
        }

        if (search) {
            search.addEventListener('input', refresh);
        }

        if (table.getAttribute('data-arp-draft') === '1') {
            tbody.addEventListener('input', refresh);
            tbody.addEventListener('change', refresh);
        }

        table.querySelectorAll('[data-arp-sort]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.getAttribute('data-arp-sort') || '';
                if (sortKey === key) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortKey = key;
                    sortDirection = 'asc';
                }
                refresh();
            });
        });

        if (reset) {
            reset.addEventListener('click', () => {
                sortKey = '';
                sortDirection = 'asc';
                refresh();
            });
        }

        refresh();
    }

    function init() {
        document.querySelectorAll(SELECTOR_GRID).forEach(bindGrid);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
