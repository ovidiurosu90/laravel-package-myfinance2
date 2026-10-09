{{-- Relative target preview on the alert form (create and edit). Fetches the closing highs / lows
     of the symbol once (price-alerts/references) and recomputes the read-only target on every
     input change. The business rule (offset direction, coverage) comes from the server; the
     script only multiplies the returned reference. The server re-resolves the target on save. --}}
<script type="module">
$(document).ready(function()
{
    var $modeInputs    = $('input[name="target_mode"]');
    var $relativeRow   = $('#relative-target-inputs');
    var $offsetInput   = $('#offset_pct');
    var $referenceKey  = $('#reference_key-select');
    var $targetInput   = $('#target_price');
    var $symbolInput   = $('#symbol-input');
    var $alertType     = $('#alert_type-select');
    var $preview       = $('#relative-target-preview');
    var $offsetSign    = $('#relative-offset-sign');
    var referencesUrl  = "{{ route('myfinance2::price-alerts.references') }}";

    var referenceData  = null;
    var referenceFetch = 0;

    var isRelative = function()
    {
        return $modeInputs.filter(':checked').val() === 'RELATIVE';
    };

    var currencyLabel = function()
    {
        var label = $.trim($('#trade_currency-label-tooltip').text());
        return label === '¤' ? '' : label;
    };

    var setPreview = function(lines)
    {
        $preview.empty();
        $.each(lines, function(_, line)
        {
            $('<div>').addClass(line.cls || '').text(line.text).appendTo($preview);
        });
        $preview.toggle(lines.length > 0);
    };

    var fireNowLine = function(target)
    {
        var live = referenceData.live_price;
        if (live === null) return null;

        var alertType = $alertType.val();
        var firesNow  = (alertType === 'PRICE_ABOVE' && live >= target)
            || (alertType === 'PRICE_BELOW' && live <= target);
        if (!firesNow) return null;

        return {
            cls: 'text-warning',
            text: 'The current price (' + referenceData.live_price_label + ') already meets this target, '
                + 'so the alert would fire on the next check.',
        };
    };

    // Same decimals as MoneyFormat::get_formatted_price($value, true), for display only.
    var formatPrice = function(value)
    {
        var abs      = Math.abs(value);
        var decimals = abs >= 1000 ? 0 : (abs >= 1 ? 2 : (abs >= 0.01 ? 4 : 6));
        return value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    };

    // One sentence joining both settings: the alert type is the crossing direction, the
    // reference and offset say where the target sits. E.g. "Fires when the price rises to or
    // above 114.00: 5% below the 52W closing high of 120.00 USD (12 Mar 2026)."
    var summaryLine = function(ref, offset, target, currency)
    {
        var crossing = $alertType.val() === 'PRICE_BELOW' ? 'falls to or below' : 'rises to or above';
        var position = offset > 0
            ? offset + '% ' + (ref.direction < 0 ? 'below' : 'above') + ' the '
            : 'at the ';

        return 'Fires when the price ' + crossing + ' ' + formatPrice(target) + (currency ? ' ' + currency : '')
            + ': ' + position + ref.reference_label + ' of ' + ref.price_label + (currency ? ' ' + currency : '')
            + ' (' + ref.date_label + ').';
    };

    var updateOffsetSign = function()
    {
        $offsetSign.text(String($referenceKey.val()).indexOf('LOW:') === 0 ? '+' : '\u2212');
    };

    var renderPreview = function()
    {
        if (!isRelative() || !referenceData) return;

        var ref    = referenceData.references[$referenceKey.val()];
        var offset = parseFloat($offsetInput.val());

        if (!ref || ref.price === null || !ref.covered) {
            $targetInput.val('').attr('title', '');
            setPreview([{ cls: 'text-danger',
                text: 'Not enough price history for this window; choose a shorter window.' }]);
            return;
        }

        if (isNaN(offset) || offset < 0) {
            $targetInput.val('').attr('title', '');
            setPreview([{ cls: 'text-danger', text: 'Enter an offset of 0% or more.' }]);
            return;
        }

        var target   = Math.round(ref.price * (1 + ref.direction * offset / 100) * 1e6) / 1e6;
        var currency = currencyLabel();
        var refText  = ref.reference_label + ' ' + ref.price_label + (currency ? ' ' + currency : '')
            + ' on ' + ref.date_label;
        var lines    = [{ cls: 'text-body', text: summaryLine(ref, offset, target, currency) }];

        if (ref.backfill) {
            lines.push({ cls: 'text-muted', text: 'Price history will be backfilled on save.' });
        }

        var fireNow = fireNowLine(target);
        if (fireNow) {
            lines.push(fireNow);
        }

        $targetInput.val(target).attr('title', refText).trigger('input');
        setPreview(lines);
    };

    var fetchReferences = function()
    {
        var symbol = $.trim($symbolInput.val()).toUpperCase();
        if (!isRelative() || !symbol) return;

        if (referenceData && referenceData.symbol === symbol) {
            renderPreview();
            return;
        }

        var myFetch = ++referenceFetch;
        setPreview([{ cls: 'text-muted', text: 'Loading the closing highs and lows...' }]);

        $.ajax({
            type: 'GET',
            url: referencesUrl,
            data: { symbol: symbol },
            success: function(data)
            {
                if (myFetch !== referenceFetch) return;
                referenceData = data;
                renderPreview();
            },
            error: function()
            {
                if (myFetch !== referenceFetch) return;
                referenceData = null;
                setPreview([{ cls: 'text-danger', text: 'Could not load the price history for this symbol.' }]);
            },
        });
    };

    var applyMode = function()
    {
        var relative = isRelative();

        // Disabled while hidden, so a FIXED alert never submits (or browser-validates) them.
        $relativeRow.toggle(relative).find('select, input').prop('disabled', !relative);
        $targetInput.prop('readonly', relative).prop('required', !relative);

        if (relative) {
            fetchReferences();
        } else {
            $targetInput.attr('title', '');
            $preview.hide();
        }
    };

    $modeInputs.on('change', applyMode);
    $offsetInput.on('input', renderPreview);
    $referenceKey.on('change', function()
    {
        updateOffsetSign();
        renderPreview();
    });
    $alertType.on('change', renderPreview);
    $symbolInput.on('blur', fetchReferences);
    $('#get-finance-data').on('click', fetchReferences);

    updateOffsetSign();
    applyMode();
});
</script>
