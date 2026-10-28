<script type="module">
    // Gains Per Year: years expand into accounts, accounts into symbols. The filters (gains / losses, search on
    // account names and symbols; overview.cards.partials.gains-per-year-filters) hide the symbols that don't
    // match, then the accounts and years left without one. While a filter is on, the year, account and footer
    // totals add up the visible symbols; with no filter the server-rendered totals are shown.
    const table = document.getElementById('gainsPerYearTable');
    if (table) {
        const searchInput = document.getElementById('gpy-search');
        const footLabel = table.querySelector('.gpy-foot-label strong');
        const yearRows = [...table.querySelectorAll('.gpy-year')];
        const accountRows = [...table.querySelectorAll('.gpy-account')];
        const symbolRows = [...table.querySelectorAll('.gpy-account-child')];
        const accountByKey = Object.fromEntries(accountRows.map((row) => [row.dataset.gpyAccount, row]));
        const expanded = new Set(); // year and account keys the user opened
        const EUR = '&euro;';

        // Mirrors MoneyFormat::get_formatted_gain (0 decimals from 1,000, else 2).
        function fmtGain(code, value)
        {
            if (value === 0) {
                return `0 ${code}`;
            }
            const abs = Math.abs(value);
            const decimals = abs >= 1000 ? 0 : 2;
            const formatted = abs.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
            return value < 0
                ? `<span class="text-danger">- ${formatted} ${code}</span>`
                : `<span class="text-success">+ ${formatted} ${code}</span>`;
        }

        // Each total cell keeps its server-rendered content (with its tooltips) next to a slot for the
        // filtered value, so clearing the filters restores it as it was.
        table.querySelectorAll('[data-gpy-col]').forEach((cell) => {
            const original = document.createElement('span');
            original.className = 'gpy-orig';
            original.append(...cell.childNodes);
            const filtered = document.createElement('span');
            filtered.className = 'gpy-filtered d-none';
            if (cell.closest('.gpy-account, tfoot')) {
                filtered.classList.add('fw-bold');
            }
            cell.append(original, filtered);
        });

        function setCell(row, col, html)
        {
            const cell = row.querySelector(`[data-gpy-col="${col}"]`);
            if (!cell) {
                return;
            }
            cell.querySelector('.gpy-orig').classList.toggle('d-none', html !== null);
            const filtered = cell.querySelector('.gpy-filtered');
            filtered.classList.toggle('d-none', html === null);
            filtered.innerHTML = html ?? '';
        }

        // A filtered total: blank when nothing adds up (as the server renders it), always shown in the footer.
        function totalHtml(code, value, alwaysShow = false)
        {
            const rounded = Math.abs(value) < 0.005 ? 0 : value;
            return rounded === 0 && !alwaysShow ? '' : fmtGain(code, rounded);
        }

        function filterState()
        {
            const active = document.querySelector('[data-gpy-mode].active');
            const mode = active ? active.dataset.gpyMode : 'all';
            const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            return { mode, query, active: mode !== 'all' || query !== '' };
        }

        function symbolMatches(row, account, state)
        {
            const total = Number(row.dataset.gpyGainEur) + Number(row.dataset.gpyDivEur);
            if ((state.mode === 'gains' && !(total > 0)) || (state.mode === 'losses' && !(total < 0))) {
                return false;
            }
            return state.query === ''
                || account.dataset.gpyName.toLowerCase().includes(state.query)
                || row.dataset.gpySymbol.toLowerCase().includes(state.query);
        }

        // Sums of the matching symbols per account, per year and overall.
        function sumMatches(state)
        {
            const sums = { accounts: {}, years: {}, grand: { gainEur: 0, divEur: 0 } };
            symbolRows.forEach((row) => {
                const accountKey = row.dataset.gpyParentAccount;
                const account = accountByKey[accountKey];
                row.gpyMatch = symbolMatches(row, account, state);
                if (!row.gpyMatch) {
                    return;
                }
                const year = account.dataset.gpyParentYear;
                const acc = sums.accounts[accountKey] ??= { gain: 0, div: 0, gainEur: 0, divEur: 0 };
                const yr = sums.years[year] ??= { gainEur: 0, divEur: 0 };
                const gainEur = Number(row.dataset.gpyGainEur);
                const divEur = Number(row.dataset.gpyDivEur);
                acc.gain += Number(row.dataset.gpyGain);
                acc.div += Number(row.dataset.gpyDiv);
                [acc, yr, sums.grand].forEach((sum) => {
                    sum.gainEur += gainEur;
                    sum.divEur += divEur;
                });
            });
            return sums;
        }

        function showTotals(state, sums)
        {
            accountRows.forEach((row) => {
                const sum = sums.accounts[row.dataset.gpyAccount];
                const code = row.dataset.gpyCode;
                setCell(row, 'gain', state.active && sum ? totalHtml(code, sum.gain) : null);
                setCell(row, 'div', state.active && sum ? totalHtml(code, sum.div) : null);
                setCell(row, 'gain-eur', state.active && sum ? totalHtml(EUR, sum.gainEur) : null);
                setCell(row, 'div-eur', state.active && sum ? totalHtml(EUR, sum.divEur) : null);
                setCell(row, 'total-eur', state.active && sum ? totalHtml(EUR, sum.gainEur + sum.divEur) : null);
            });
            yearRows.forEach((row) => {
                const sum = sums.years[row.dataset.gpyYear];
                setCell(row, 'gain-eur', state.active && sum ? totalHtml(EUR, sum.gainEur) : null);
                setCell(row, 'div-eur', state.active && sum ? totalHtml(EUR, sum.divEur) : null);
                setCell(row, 'total-eur', state.active && sum ? totalHtml(EUR, sum.gainEur + sum.divEur) : null);
            });
            const foot = table.querySelector('tfoot tr');
            const grand = sums.grand;
            setCell(foot, 'gain-eur', state.active ? totalHtml(EUR, grand.gainEur, true) : null);
            setCell(foot, 'div-eur', state.active ? totalHtml(EUR, grand.divEur, true) : null);
            setCell(foot, 'total-eur', state.active ? totalHtml(EUR, grand.gainEur + grand.divEur, true) : null);
            if (footLabel) {
                footLabel.textContent = state.active ? 'Total (filtered)' : 'Total';
            }
        }

        function setChevron(row, open)
        {
            const chevron = row.querySelector('.gpy-chevron');
            if (chevron) {
                chevron.style.transform = open ? 'rotate(90deg)' : '';
            }
        }

        // autoExpand: a search opens every year and account with a match, so the matches are in view.
        function render(autoExpand = false)
        {
            const state = filterState();
            const sums = sumMatches(state);
            if (autoExpand) {
                expanded.clear();
                if (state.query !== '') {
                    Object.keys(sums.years).forEach((year) => expanded.add(year));
                    Object.keys(sums.accounts).forEach((key) => expanded.add(key));
                }
            }
            yearRows.forEach((row) => {
                const year = row.dataset.gpyYear;
                row.style.display = !state.active || sums.years[year] ? '' : 'none';
                setChevron(row, expanded.has(year));
            });
            accountRows.forEach((row) => {
                const key = row.dataset.gpyAccount;
                const visible = (!state.active || sums.accounts[key]) && expanded.has(row.dataset.gpyParentYear);
                row.style.display = visible ? '' : 'none';
                setChevron(row, expanded.has(key));
            });
            symbolRows.forEach((row) => {
                const account = accountByKey[row.dataset.gpyParentAccount];
                const visible = row.gpyMatch && account.style.display !== 'none'
                    && expanded.has(row.dataset.gpyParentAccount);
                row.style.display = visible ? '' : 'none';
            });
            showTotals(state, sums);
        }

        table.addEventListener('click', (e) => {
            const accountRow = e.target.closest('.gpy-account');
            const yearRow = e.target.closest('.gpy-year');
            if (accountRow) {
                const key = accountRow.dataset.gpyAccount;
                if (expanded.has(key)) {
                    expanded.delete(key);
                } else {
                    expanded.add(key);
                }
            } else if (yearRow) {
                const year = yearRow.dataset.gpyYear;
                if (expanded.has(year)) {
                    // Closing a year also closes its accounts.
                    expanded.delete(year);
                    accountRows.filter((row) => row.dataset.gpyParentYear === year)
                        .forEach((row) => expanded.delete(row.dataset.gpyAccount));
                } else {
                    expanded.add(year);
                }
            } else {
                return;
            }
            render();
        });
        const modeButtons = document.querySelectorAll('[data-gpy-mode]');
        modeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                modeButtons.forEach((other) => {
                    other.classList.toggle('active', other === button);
                    other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                });
                render();
            });
        });
        if (searchInput) {
            searchInput.addEventListener('input', () => render(true));
        }
    }
</script>
