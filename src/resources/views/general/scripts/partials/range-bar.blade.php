{{--
    Shared 52-week range bar used by the symbol chart modal and the orders chart panel.

    Defines attachRangeBarTooltips() and buildRangeBar(data, cur) in the including scope. The only
    thing that differs between the two callers is the bar element id, passed in as $rangeBarId; the
    including scope must also provide fmtPrice() (see general.scripts.partials.fmt-price).

    Usage:
        @include('myfinance2::general.scripts.partials.range-bar', ['rangeBarId' => 'scm-range-bar'])
--}}
@php($rangeBarId = $rangeBarId ?? 'range-bar')
    // Lightweight hover tooltip for the range bar's [data-tooltip] elements (Bootstrap tooltips are
    // not run over this dynamically injected markup). Shared by the chart modal and orders panel.
    function attachRangeBarTooltips()
    {
        document.querySelectorAll('#{{ $rangeBarId }} [data-tooltip]').forEach((el) =>
        {
            const text = el.getAttribute('data-tooltip');
            let tip = null;
            el.addEventListener('mouseenter', () =>
            {
                tip = document.createElement('div');
                tip.textContent = text;
                tip.style.cssText = 'position:fixed;background:#000;color:#fff;padding:4px 8px;'
                    + 'border-radius:4px;font-size:0.75rem;z-index:9999;pointer-events:none;'
                    + 'max-width:280px;white-space:pre-line;';
                document.body.appendChild(tip);
                const r = el.getBoundingClientRect();
                tip.style.left = Math.max(4, r.left + r.width / 2 - tip.offsetWidth / 2) + 'px';
                tip.style.top = (r.top - tip.offsetHeight - 5) + 'px';
            });
            el.addEventListener('mouseleave', () => { if (tip) { tip.remove(); tip = null; } });
        });
    }

    // 52-week range bar. Primary range is the closing-based 52W high/low (highest / lowest daily
    // close over the past year, each dated); the current price sits between them. Yahoo's 52W
    // intraday high/low is the secondary, explained in the marker tooltip. Falls back to the
    // intraday range only when no closing range is available.
    function buildRangeBar(data, cur)
    {
        const price = data.price;
        const $bar  = $('#{{ $rangeBarId }}');

        const cHigh = data.closingHigh;
        const cLow  = data.closingLow;
        const useClosing = (cHigh != null && cLow != null && cHigh > cLow);

        const high = useClosing ? cHigh : data.fiftyTwoWeekHigh;
        const low  = useClosing ? cLow  : data.fiftyTwoWeekLow;

        if (high == null || low == null || price == null || high <= low) {
            $bar.html('');
            return;
        }

        let pos = ((price - low) / (high - low)) * 100;
        pos = Math.max(0, Math.min(100, pos));

        const belowHigh = ((high - price) / high * 100).toFixed(2);
        const aboveLow  = ((price - low) / low * 100).toFixed(2);

        const lowLabel   = fmtPrice(low) + ' ' + cur;
        const highLabel  = fmtPrice(high) + ' ' + cur;
        const priceLabel = fmtPrice(price) + ' ' + cur;

        const kind = useClosing ? 'closing ' : '';
        const lowDate  = useClosing && data.closingLowDate ? ' (' + data.closingLowDate + ')' : '';
        const highDate = useClosing && data.closingHighDate ? ' (' + data.closingHighDate + ')' : '';

        // Secondary line: Yahoo's intraday extremes, the single highest/lowest prices touched
        // intraday over the year. They can exceed the closing range and carry no date.
        let secondary = '';
        if (useClosing && data.fiftyTwoWeekHigh != null && data.fiftyTwoWeekLow != null) {
            secondary = '\nIntraday (Yahoo): high ' + fmtPrice(data.fiftyTwoWeekHigh) + ' ' + cur
                + ', low ' + fmtPrice(data.fiftyTwoWeekLow) + ' ' + cur
                + '. Single intraday extremes, can exceed the closing range; no date available.';
        }

        const markerTip = belowHigh + '% below the 52W ' + kind + 'high' + highDate
            + ' · ' + aboveLow + '% above the 52W ' + kind + 'low' + lowDate + secondary;
        const lowTip  = useClosing
            ? 'Lowest daily close over the past year, on ' + data.closingLowDate
                + '. Primary 52W low.'
            : 'Yahoo 52-week intraday low.';
        const highTip = useClosing
            ? 'Highest daily close over the past year, on ' + data.closingHighDate
                + '. Primary 52W high.'
            : 'Yahoo 52-week intraday high.';

        const caption = '▼ ' + belowHigh + '% high · ▲ ' + aboveLow + '% low';
        const captionLabel = useClosing ? '52W Range (closing)' : '52W Range';

        $bar.html(
            '<div style="padding-top:20px;">'
          +   '<div style="display:flex;align-items:center;gap:4px;'
          +       'font-size:0.72rem;color:#000;">'
          +     '<span data-tooltip="' + lowTip + '" style="white-space:nowrap;">'
          +         lowLabel + '</span>'
          +     '<div style="flex:1;position:relative;height:5px;background:#e0e0e0;'
          +         'border-radius:3px;min-width:60px;">'
          +       '<span style="position:absolute;left:' + pos + '%;'
          +           'transform:translateX(-50%);bottom:calc(100% + 5px);'
          +           'font-size:0.875rem;white-space:nowrap;color:#555;">'
          +         priceLabel + '</span>'
          +       '<div data-tooltip="' + markerTip + '" style="position:absolute;'
          +           'width:8px;height:8px;background:#555;border-radius:1px;'
          +           'top:-1.5px;transform:translateX(-50%);left:' + pos + '%;"></div>'
          +     '</div>'
          +     '<span data-tooltip="' + highTip + '" style="white-space:nowrap;">'
          +         highLabel + '</span>'
          +   '</div>'
          +   '<div style="font-size:0.68rem;color:#6c757d;text-align:center;'
          +       'margin-top:6px;white-space:nowrap;">'
          +     captionLabel + '&nbsp;&nbsp;' + caption + '</div>'
          + '</div>'
        );

        attachRangeBarTooltips();
    }
