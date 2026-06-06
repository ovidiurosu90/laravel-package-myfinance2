    var tradeCurrencies = {!! json_encode($tradeCurrencies) !!};
    var tradeCurrenciesByIsoCode = {};
    for (let i in tradeCurrencies) {
        tradeCurrenciesByIsoCode[tradeCurrencies[i]['iso_code']] = tradeCurrencies[i];
    }

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    var fetchedPrice          = null;
    var fetchedHigh           = null;
    var fetchedLow            = null;
    var fetchedCurrencySymbol = '$';
    var lastAutoCurrencyId    = null;

    var decodeHtml = function(html)
    {
        var txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    };

    var buildNoteText = function(limitPrice, currentPrice, high52w, low52w, currencySymbol)
    {
        if (!currentPrice || !high52w || !low52w) return null;
        var sym = currencySymbol || '$';

        var pctVsCurrent = (currentPrice - limitPrice) / currentPrice * 100;
        var vsCurrentDir = pctVsCurrent >= 0 ? 'below' : 'above';
        pctVsCurrent     = Math.abs(pctVsCurrent).toFixed(1);

        var pctVsHigh = (high52w - limitPrice) / high52w * 100;
        var vsHighDir = pctVsHigh >= 0 ? 'below' : 'above';
        pctVsHigh     = Math.abs(pctVsHigh).toFixed(1);

        var pctVsLow = (limitPrice - low52w) / low52w * 100;
        var vsLowDir = pctVsLow >= 0 ? 'above' : 'below';
        pctVsLow     = Math.abs(pctVsLow).toFixed(1);

        return pctVsCurrent + '% ' + vsCurrentDir + ' current price; '
            + pctVsHigh + '% ' + vsHighDir + ' 52W high of ' + sym
            + parseFloat(high52w).toFixed(2) + '; '
            + pctVsLow + '% ' + vsLowDir + ' 52W low of ' + sym
            + parseFloat(low52w).toFixed(2);
    };

    var storeFetchedData = function(data)
    {
        fetchedPrice = data.price;
        fetchedHigh  = data.fiftyTwoWeekHigh;
        fetchedLow   = data.fiftyTwoWeekLow;
        if (tradeCurrenciesByIsoCode[data.currency]) {
            fetchedCurrencySymbol = decodeHtml(
                tradeCurrenciesByIsoCode[data.currency]['display_code'] || '$'
            );
        }
        var $name = $('#fetched-symbol-name');
        $name.find('span').html(data.name);
        $name.show();
    };

    var setFetchedTradeCurrency = function(data)
    {
        if (!tradeCurrenciesByIsoCode[data.currency]) return;
        var newId = parseInt(tradeCurrenciesByIsoCode[data.currency]['id']);
        var tcSelectize = $('#trade_currency-select')[0].selectize;
        if (!tcSelectize) return;
        var currentId = parseInt(tcSelectize.getValue()) || null;
        if (!currentId || currentId === lastAutoCurrencyId) {
            tcSelectize.setValue(newId);
            lastAutoCurrencyId = newId;
        }
    };

    // Surface the fetched currency in the read-only label to the right of the
    // Trade Currency field (where present), formatted as "Name (symbol)".
    var showFetchedTradeCurrency = function(data)
    {
        if (!data.currency) return;

        var tc    = tradeCurrenciesByIsoCode[data.currency];
        var label = tc
            ? tc['name'] + ' (' + decodeHtml(tc['display_code']) + ')'
            : data.currency;

        $('#fetched-trade-currency').find('span').text(label).end().show();
    };
