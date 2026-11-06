{{-- The order form's prefill from the symbol's quote (get-finance-data, with order_sides).

     The form never picks the action: the link that opened it may set it (an alert, an email, a clone, the
     watchlist's Buy / Sell), else the user does (orders/scripts/action-pick). The fields that depend on the side
     (limit price 1 % from the price, quantity, account, rationale) are filled once a side is set and refilled when
     it or the account changes, as long as the user has not typed their own value: a field counts as automatic while
     it is empty or still holds the value this script put there. The exchange rate follows the account the same way
     (the BUY and SELL sides can suggest accounts in different currencies). Values from the URL count as the
     user's. --}}
<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput         = $('#symbol-input');
    var $limitPriceInput     = $('#limit_price');
    var $fetchedLimitPrice   = $('#fetched-limit-price');
    var $fetchedDescription  = $('#fetched-description');
    var $quantityInput       = $('#quantity-input');
    var $descriptionInput    = $('#description');
    var $exchangeRateInput   = $('#exchange_rate');
    var $accountNote         = $('#account-note');
    var $orderBanner         = $('#order-summary-banner');

    var orderSides           = null;
    var weakSignal           = false;
    var lastAutoDescription  = null;
    var fetchCounter         = 0;
    var lastAuto             = { limit: null, qty: null, account: null, fx: null };

    // In label-only mode (edit) the regenerated description is surfaced next to
    // the Description label; it never overwrites the textarea or other inputs.
    var labelOnly            = {{ !empty($labelOnly) ? 'true' : 'false' }};

    var actionSelectize  = function() { return $('#action-select')[0].selectize; };
    var accountSelectize = function() { return $('#account-select')[0].selectize; };

    // Whether a field still holds what this script filled (or nothing), so it may be refilled.
    var isAuto = function(current, last)
    {
        return current === '' || current === null || (last !== null && String(current) === last);
    };

    // The rationale text for the current limit price: the price relationships
    // (vs current / 52W high / 52W low), prefixed with "weak signal" for a buy
    // within 15% of the 52-week high.
    var buildReasonText = function()
    {
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return null;

        var limitPrice = parseFloat($limitPriceInput.val());
        if (isNaN(limitPrice) || limitPrice <= 0) return null;

        var text = buildNoteText(limitPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!text) return null;

        if (weakSignal) {
            text = 'weak signal; ' + text;
        }
        return text;
    };

    var showFetchedDescription = function(text)
    {
        if (text === undefined) text = buildReasonText();
        if (!text) { $fetchedDescription.hide(); return; }

        $fetchedDescription.find('span').text(text).end().show();
    };

    // The rationale follows the limit price unless the user wrote their own.
    var refreshReason = function()
    {
        var reasonText = buildReasonText();
        $orderBanner.data('reason', reasonText).data('weak-signal', weakSignal ? 1 : 0);

        if (reasonText && (!$descriptionInput.val() || $descriptionInput.val() === lastAutoDescription)) {
            $descriptionInput.val(reasonText);
            lastAutoDescription = reasonText;
        }
        $orderBanner.trigger('banner-update');
    };

    // "Also held in": a SELL from one account while others hold the symbol too.
    var showAccountNote = function(side)
    {
        var accountId = accountSelectize() ? accountSelectize().getValue() : '';
        var others    = (orderSides && side === 'SELL' ? orderSides.holdings : [])
            .filter(function(h) { return String(h.account_id) !== String(accountId); });
        if (!others.length) { $accountNote.hide(); return; }

        $accountNote.find('span').text(others.map(function(h)
        {
            return h.name + ' (' + h.quantity + ')';
        }).join(', ')).end().show();
    };

    var fillField = function($input, key, value)
    {
        if (!isAuto($input.val(), lastAuto[key])) return;
        if (value === null || value === undefined) {
            if (lastAuto[key] !== null) { $input.val(''); }
            lastAuto[key] = null;
            return;
        }
        $input.val(value);
        lastAuto[key] = String(value);
    };

    // Fill the side's figures into the fields still automatic. Setting the account
    // fires its change event, which refetches with it (the quantity and the
    // available quantity depend on the account).
    var applySide = function()
    {
        var side = actionSelectize() ? actionSelectize().getValue() : '';
        var s    = orderSides && side ? orderSides[side] : null;
        showAccountNote(side);
        if (!s) {
            weakSignal = false;
            refreshReason();
            return;
        }
        weakSignal = !!s.weak_signal;

        fillField($limitPriceInput, 'limit', s.limit_price);
        fillField($quantityInput, 'qty', s.suggested_qty);

        var account = accountSelectize();
        if (account && s.account_id && isAuto(account.getValue(), lastAuto.account)
            && String(s.account_id) !== String(account.getValue()))
        {
            lastAuto.account = String(s.account_id);
            account.setValue(s.account_id);
        }

        refreshReason();
        // The tier reading and the banner follow the quantity and limit price.
        $quantityInput.trigger('input');
    };

    var applySmartPrefill = function(symbol)
    {
        var accountId = accountSelectize() ? accountSelectize().getValue() : '';
        var myFetch = ++fetchCounter;

        $.ajax({
            type: 'GET',
            url:  "{{ route('myfinance2::get-finance-data') }}",
            data: { symbol: symbol, timestamp: null, account_id: accountId || null, order_sides: 1 },
            success: function(data)
            {
                if (myFetch !== fetchCounter) return;
                var s = data.suggestion;
                orderSides = s.order_sides || null;
                storeFetchedData(data);

                $fetchedLimitPrice.find('span')
                    .text(data.price)
                    .attr('data-bs-original-title', fetchedPriceTitle(data))
                    .end().show();

                // Trade currency tracks the symbol, so keep the select in sync.
                setFetchedTradeCurrency(data);
                window.handleAvailableQuantity(data.available_quantity);
                $(document).trigger('order-sides-loaded', [orderSides]);

                // Edit: the limit price is already set, so the rationale can be
                // computed now and surfaced in the label without mutating inputs.
                if (labelOnly) {
                    var side   = actionSelectize() ? actionSelectize().getValue() : '';
                    weakSignal = !!(orderSides && orderSides[side] && orderSides[side].weak_signal);
                    var labelReason = buildReasonText();
                    $orderBanner
                        .data('reason', labelReason)
                        .data('weak-signal', weakSignal ? 1 : 0);
                    showFetchedDescription(labelReason);
                    $orderBanner.trigger('banner-update');
                    return;
                }

                // For the account the figures were computed for (refetched when it changes); cleared
                // while automatic when that account trades in the symbol's currency.
                fillField($exchangeRateInput, 'fx', s.exchange_rate || null);

                applySide();
            },
        });
    };

    $limitPriceInput.on('input', function()
    {
        var newText = buildReasonText();

        if (labelOnly) {
            showFetchedDescription(newText);
            if (newText) {
                $orderBanner.data('reason', newText).trigger('banner-update');
            }
            return;
        }

        if (!newText) return;

        $orderBanner.data('reason', newText).trigger('banner-update');

        if ($descriptionInput.val() === lastAutoDescription) {
            $descriptionInput.val(newText);
            lastAutoDescription = newText;
        }
    });

    if (!labelOnly) {
        $('#action-select').on('change', applySide);
        $('#account-select').on('change', function()
        {
            var symbol = $symbolInput.val();
            if (symbol) { applySmartPrefill(symbol); }
        });
    }

    @include('myfinance2::general.scripts.partials.finance-symbol-triggers')
});
</script>
