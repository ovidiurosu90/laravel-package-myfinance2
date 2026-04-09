<script type="module">
$(document).ready(function ()
{
    // ── Date picker (date-only, no time) ────────────────────────────────────
    window.splitDatePicker = new TempusDominus(
        document.getElementById('split-date-picker'),
        {
            localization: { format: 'yyyy-MM-dd' },
            display: {
                components: { clock: false, hours: false, minutes: false, seconds: false },
                buttons:    { today: true },
            },
            restrictions: {
                maxDate: new Date(),
            },
        }
    );
    $('input[data-td-target="#split-date-picker"]').attr('placeholder', 'Pick split date');

    // Re-render preview when date changes (filter trades by date)
    document.getElementById('split-date-picker').addEventListener('change.td', function ()
    {
        renderPreview();
    });

    // ── Symbol Selectize ────────────────────────────────────────────────────
    var $symbolSelect = $("#symbol-select").selectize({
        placeholder: ' {{ trans('myfinance2::splits.forms.item-form.symbol.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function (value)
        {
            if (!value) {
                window._splitTrades = [];
                $('#open-trades-loading').hide();
                $('#open-trades-content').hide();
                $('#open-trades-pre-select').show();
                return;
            }
            fetchOpenTrades(value);
        },
    });

    var symbolInitialValue = @json($symbol ?? '');
    var symbolSelectize = $symbolSelect[0].selectize;
    if (symbolSelectize && symbolInitialValue) {
        if (!symbolSelectize.options[symbolInitialValue]) {
            symbolSelectize.addOption({ value: symbolInitialValue, text: symbolInitialValue });
        }
        symbolSelectize.setValue(symbolInitialValue, true);
        fetchOpenTrades(symbolInitialValue);
    }

    // ── Ratio input → live preview update ──────────────────────────────────
    $('#ratio_numerator').on('input', function () { renderPreview(); });

    // ── AJAX: fetch all trades for a symbol ────────────────────────────────
    // Date filtering is done client-side in renderPreview()
    function fetchOpenTrades(symbol)
    {
        window._splitTrades = [];
        $('#open-trades-pre-select').hide();
        $('#open-trades-loading').show();
        $('#open-trades-content').hide();

        $.ajax({
            type:    'GET',
            url:     "{{ url('/get-trades') }}",
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data:    { symbol: symbol },
            success: function (data)
            {
                window._splitTrades = data.trades || [];
                $('#open-trades-loading').hide();
                $('#open-trades-content').show();
                renderPreview();
            },
            error: function ()
            {
                window._splitTrades = [];
                $('#open-trades-loading').hide();
                $('#open-trades-content').show();
                $('#open-trades-empty').text('Could not load trades.').show();
                $('#open-trades-table-wrap').hide();
            },
        });
    }

    // ── Render preview table ────────────────────────────────────────────────
    // Filters by split_date (if set): only trades with date <= split_date are shown.
    function renderPreview()
    {
        var allTrades = window._splitTrades || [];
        var ratio     = parseInt($('#ratio_numerator').val(), 10);
        var splitDate = $('input[data-td-target="#split-date-picker"]').val().trim();

        // Filter by date if split_date is set (ISO string comparison is safe for YYYY-MM-DD)
        var trades = splitDate
            ? allTrades.filter(function (t) { return t.date <= splitDate; })
            : allTrades;

        if (!trades.length) {
            $('#open-trades-empty').show();
            $('#open-trades-table-wrap').hide();
            return;
        }

        $('#open-trades-empty').hide();
        $('#open-trades-table-wrap').show();

        var hasRatio = ratio >= 2;
        var rows = trades.map(function (t)
        {
            var qtyDisplay, priceDisplay;

            if (hasRatio) {
                var newQty   = +parseFloat((t.quantity * ratio).toFixed(6));
                var newPrice = +parseFloat((t.unit_price / ratio).toFixed(4));
                qtyDisplay   = t.quantity + 'x &rarr; <strong>' + newQty + 'x</strong>';
                priceDisplay = t.unit_price + ' ' + t.trade_currency
                    + ' &rarr; <strong>' + newPrice + ' ' + t.trade_currency + '</strong>';
            } else {
                qtyDisplay   = t.quantity + 'x';
                priceDisplay = t.unit_price + ' ' + t.trade_currency;
            }

            return '<tr>'
                + '<td class="text-nowrap">' + $('<span>').text(t.account).html() + '</td>'
                + '<td class="text-nowrap">' + t.date + '</td>'
                + '<td><span class="badge '
                    + (t.action === 'BUY' ? 'bg-success' : 'bg-danger')
                    + '">' + t.action + '</span></td>'
                + '<td class="text-nowrap">' + qtyDisplay + '</td>'
                + '<td class="text-nowrap">' + priceDisplay + '</td>'
                + '</tr>';
        });

        $('#open-trades-tbody').html(rows.join(''));
    }
});
</script>
