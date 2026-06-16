@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')
@use('ovidiuro\myfinance2\App\Services\DipBuyingBacktestService')

<script type="module">

@include('myfinance2::positions.scripts._formatters')

// Metric metadata and detected drop episodes (effective-drawdown axis). $dipChart is provided by the
// controller (DipBuyingBacktestService::chartContext), the same context the card header renders.
const dipMetrics  = @json(DipBuyingBacktestService::chartMetrics());
const dipEpisodes = @json($dipChart['episodes']);
// "Where are we now": the current drop in the tail, measured from the most recent local peak (a
// navigation aid, drawn distinctly from the historical episodes). Null when none clears the threshold.
const dipCurrentDrop = @json($dipChart['current_drop'] ?? null);
// The exact drop-axis drawdown series (negated, so it dips) and the active threshold, so the chart
// can draw the very signal the episodes were detected on plus its enter/exit cutoffs.
const dipDropSeries = @json($dipChart['series']);
const dipMinDrop    = @json($dipChart['min_drop']);
// The active "Episodes on" basis decides which non-cash line is shown initially: effective ->
// drawdown, change -> change %, vusa -> VUSA.AS. Cash is always shown; the rest start hidden but
// can be toggled back on from the legend.
const dipDropMode   = @json($dipChart['drop_mode'] ?? 'effective');
// Current EUR market value of the user's VUSA.AS holding across accounts (null if none held), shown
// above the VUSA bar; the gain % stays on the bar itself.
const dipVusaMvalue = @json($dipChart['vusa_mvalue_eur']);

// Reuse the precomputed user-overview series (EUR), no recompute. VUSA is loaded as raw price and
// normalized to a percent change from the window start so its dips line up on the percentage axis.
const dipSeriesData = {
    changePercentage: {!! $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id, 'changePercentage_EUR') !!},
    change:           {!! $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id, 'change_EUR') !!},
    cash:             {!! $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id, 'cash_EUR') !!},
    // Cost basis, not plotted; only its latest value, to show cash as a % of cost like /positions.
    cost:             {!! $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id, 'cost_EUR') !!},
    // Portfolio market value, not plotted; only its latest value, to put a EUR figure on the drawdown.
    mvalue:           {!! $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id, 'mvalue_EUR') !!},
};
const dipVusaRaw = {!! $ChartsBuilder::getChartSymbolAsJsonString('VUSA.AS') !!};

function dipGetLast(arr)
{
    return (arr && arr.length) ? arr[arr.length - 1].value : null;
}

function dipRangeMinMax(arr)
{
    let min = null;
    let max = null;
    (arr || []).forEach((d) =>
    {
        if (min === null || d.value < min) min = d.value;
        if (max === null || d.value > max) max = d.value;
    });
    return { min, max };
}

// Min/max of a per-date ratio (numerator / denominator * 100), matching the /positions cash bar.
function dipPctRangeMinMax(numArr, denArr)
{
    const denByDate = {};
    (denArr || []).forEach((d) => { denByDate[d.time] = d.value; });
    let min = null;
    let max = null;
    (numArr || []).forEach((d) =>
    {
        const den = denByDate[d.time];
        if (den && den !== 0)
        {
            const pct = (d.value / den) * 100;
            if (min === null || pct < min) min = pct;
            if (max === null || pct > max) max = pct;
        }
    });
    return { min, max };
}

function dipRenderRangeBar(elementId, min, max, current, currentLabel, labelColor, fmt)
{
    if (min === null || max === null || current === null) return;
    const range = max - min;
    const position = range === 0
        ? 50 : Math.max(0, Math.min(100, ((current - min) / range) * 100));
    $('#' + elementId).html(
        '<div style="padding-top:20px;"><div style="display:flex;align-items:center;gap:4px;'
        + 'font-size:0.72rem;color:#000;">'
        + '<span style="white-space:nowrap;">' + fmt(min) + '</span>'
        + '<div style="flex:1;position:relative;height:5px;background:#e0e0e0;border-radius:3px;'
        + 'min-width:40px;">'
        + '<span style="position:absolute;left:' + position + '%;transform:translateX(-50%);'
        + 'bottom:calc(100% + 5px);font-size:0.875rem;white-space:nowrap;color:' + labelColor + ';">'
        + currentLabel + '</span>'
        + '<div style="position:absolute;width:8px;height:8px;background:#555;border-radius:1px;'
        + 'top:-1.5px;transform:translateX(-50%);left:' + position + '%;"></div>'
        + '</div><span style="white-space:nowrap;">' + fmt(max) + '</span></div></div>'
    );
}

$(document).ready(function()
{
    const eur = createCurrencyFormatter('de-DE');
    const pct = createPercentageFormatter();
    const fmtEur = (v) => eur(v, 'EUR');
    const fmtPct = (v) => pct(v);

    // Normalize VUSA price to percent change from the first available point.
    const dipVusa = (function ()
    {
        if (!dipVusaRaw || !dipVusaRaw.length) return [];
        const base = parseFloat(dipVusaRaw[0].value);
        if (!(base > 0)) return [];
        return dipVusaRaw.map((p) => ({ time: p.time, value: (parseFloat(p.value) / base - 1) * 100 }));
    })();
    const dipData = {
        effective: dipDropSeries,
        changePercentage: dipSeriesData.changePercentage,
        vusa: dipVusa,
        change: dipSeriesData.change,
        cash: dipSeriesData.cash,
    };

    // Every chart line is a percentage, so cash is plotted as % of cost (right axis); the EUR series
    // stays in dipData for the cash bar and value readout.
    const dipCashPct = (function ()
    {
        const costByDate = {};
        (dipSeriesData.cost || []).forEach((d) => { costByDate[d.time] = d.value; });
        const out = [];
        (dipSeriesData.cash || []).forEach((d) =>
        {
            const cost = costByDate[d.time];
            if (cost && cost !== 0) out.push({ time: d.time, value: (d.value / cost) * 100 });
        });
        return out;
    })();

    const el = document.getElementById('chart-dipDrawdown');
    if (!el || typeof LightweightCharts === 'undefined') return;

    const chart = LightweightCharts.createChart(el, {
        width: el.clientWidth,
        height: 250,
        layout: { attributionLogo: false },
        leftPriceScale: { visible: true },
        rightPriceScale: { visible: true },
    });

    const series = {};
    Object.keys(dipMetrics).forEach((metric) =>
    {
        const meta   = dipMetrics[metric];
        const isLeft = meta.axis === 'left';
        series[metric] = chart.addSeries(LightweightCharts.BaselineSeries, {
            lineColor: meta.color,
            topLineColor: meta.color,
            bottomLineColor: meta.color,
            lineStyle: meta.style,
            priceScaleId: isLeft ? 'left' : 'right',
            priceFormat: {
                type: 'custom',
                minMove: 0.01,
                formatter: fmtPct,
            },
        });
        series[metric].setData((metric === 'cash' ? dipCashPct : dipData[metric]) || []);
    });

    chart.priceScale('right').applyOptions({ scaleMargins: { top: 0.1, bottom: 0.1 } });
    chart.priceScale('left').applyOptions({ scaleMargins: { top: 0.2, bottom: 0.2 } });

    // Draw the enter (T) and exit (T/2) cutoffs on the drop axis, so it is visible where each band
    // opens (drawdown crosses below enter) and closes (recovers above exit). Drawdown is negated.
    if (series.effective && dipMinDrop) {
        series.effective.createPriceLine({
            price: -dipMinDrop, color: 'rgba(220,53,69,0.9)', lineWidth: 1, lineStyle: 2,
            axisLabelVisible: false, title: '',
        });
        series.effective.createPriceLine({
            price: -dipMinDrop / 2, color: 'rgba(220,53,69,0.4)', lineWidth: 1, lineStyle: 1,
            axisLabelVisible: false, title: '',
        });
    }

    // Status values (the big number) sit in the first row; each bar carries only a percentage above
    // its marker (VUSA gain, portfolio return, cash as % of cost), with the EUR/% min and max on the
    // ends. Change % stays a chart line only, no bar (like /positions).
    const vuLast   = dipGetLast(dipData.vusa);
    const chLast   = dipGetLast(dipData.change);
    const caLast   = dipGetLast(dipData.cash);
    const dwLast   = dipGetLast(dipData.effective); // current drop-axis drawdown (negated, so <= 0)
    const cpLast   = dipGetLast(dipData.changePercentage); // portfolio return, shown under Change
    const setStat  = (id, html, color) => $('#' + id).css('color', color).html(html);
    // VUSA shows the EUR market value of the held position above the bar, labelled like /positions
    // (value followed by the metric name); the gain % stays on the bar. Falls back to the gain %
    // when no VUSA.AS is held.
    if (dipVusaMvalue !== null) {
        setStat('dipchart-vusa-status', fmtEur(dipVusaMvalue) + ' VUSA.AS', dipMetrics.vusa.color);
    } else if (vuLast !== null) {
        setStat('dipchart-vusa-status', fmtPct(vuLast) + ' VUSA.AS', dipMetrics.vusa.color);
    }
    if (chLast !== null) setStat('dipchart-change-status', fmtEur(chLast) + ' change', dipMetrics.changePercentage.color);
    // Drawdown gets a EUR figure consistent with the % on the bar: at f% off the peak, the peak value
    // was mvalue/(1 - f), so the euro loss is mvalue * f/(1 - f). Shown negative, like the %.
    let ddEur = 0;
    if (dwLast !== null) {
        const mvalueLast = dipGetLast(dipSeriesData.mvalue);
        const ddFraction = -dwLast / 100;
        ddEur = (mvalueLast && ddFraction > 0 && ddFraction < 1)
            ? mvalueLast * ddFraction / (1 - ddFraction) : 0;
        setStat('dipchart-drawdown-status', fmtEur(-ddEur) + ' Portfolio vs VUSA.AS', dipMetrics.effective.color);
    }
    if (caLast !== null) setStat('dipchart-cash-status', fmtEur(caLast) + ' cash', dipMetrics.cash.color);

    const vuR = dipRangeMinMax(dipData.vusa);
    dipRenderRangeBar('dipchart-vusa-range-bar', vuR.min, vuR.max, vuLast,
        vuLast !== null ? fmtPct(vuLast) : null, dipMetrics.vusa.color, fmtPct);

    // Change: the whole bar is in portfolio return % (min, max and marker), like /positions, so its
    // marker uses the Change % (purple) color, not the Change (blue) one.
    const chR = dipRangeMinMax(dipData.changePercentage);
    dipRenderRangeBar('dipchart-change-range-bar', chR.min, chR.max, cpLast,
        cpLast !== null ? fmtPct(cpLast) : '', dipMetrics.changePercentage.color, fmtPct);

    // Drawdown: the drop-axis (per the "Drop on" mode) in %; deepest to shallowest, marker at current.
    const dwR = dipRangeMinMax(dipData.effective);
    dipRenderRangeBar('dipchart-drawdown-range-bar', dwR.min, dwR.max, dwLast,
        dwLast !== null ? fmtPct(dwLast) : '', dipMetrics.effective.color, fmtPct);

    // Cash: the whole bar is in cash-as-%-of-cost (min, max and marker), like /positions.
    const caR = dipPctRangeMinMax(dipData.cash, dipSeriesData.cost);
    const costLast = dipGetLast(dipSeriesData.cost);
    const cashPct  = (caLast !== null && costLast) ? (caLast / costLast) * 100 : null;
    dipRenderRangeBar('dipchart-cash-range-bar', caR.min, caR.max, cashPct,
        cashPct !== null ? fmtPct(cashPct) : '', dipMetrics.cash.color, fmtPct);

    // Worked example under the chart: connect the top bars to the effective drawdown with live numbers.
    // Each metric's drawdown is how far its current marker sits below its own peak (the bar's max);
    // Portfolio vs VUSA.AS is the worse of your portfolio's and VUSA.AS's. Same numbers as the bars.
    const dipDdFromPeak = (now, peak) =>
    {
        const idxNow = 1 + now / 100;
        const idxPeak = 1 + peak / 100;
        return idxPeak > 0 ? Math.max(0, (1 - idxNow / idxPeak) * 100) : 0;
    };
    if (cpLast !== null && vuLast !== null && chR.max !== null && vuR.max !== null)
    {
        const pfDd  = dipDdFromPeak(cpLast, chR.max);
        const vuDd  = dipDdFromPeak(vuLast, vuR.max);
        const effDd = (dwLast !== null) ? -dwLast : Math.max(pfDd, vuDd);
        const f2    = (v) => (Math.round(v * 100) / 100).toFixed(2);
        const cCh   = dipMetrics.changePercentage.color;
        const cVu   = dipMetrics.vusa.color;
        const cEf   = dipMetrics.effective.color;
        // Tint a value with its metric color (bold for the drawdown results, plain for returns/peaks).
        const tint  = (txt, color) => '<span style="color:' + color + ';">' + txt + '</span>';
        const tintB = (txt, color) => '<strong style="color:' + color + ';">' + txt + '</strong>';

        const noteEl = document.getElementById('dipchart-worse-note');
        if (noteEl)
        {
            noteEl.innerHTML =
                'Right now: your ' + tint('portfolio', cCh) + ' is at '
                + tint(fmtPct(cpLast), cCh) + ' vs its peak ' + tint(fmtPct(chR.max), cCh)
                + ', so down ' + tintB(f2(pfDd) + '%', cCh) + '; '
                + tint('VUSA.AS', cVu) + ' at ' + tint(fmtPct(vuLast), cVu) + ' vs '
                + tint(fmtPct(vuR.max), cVu) + ', down ' + tintB(f2(vuDd) + '%', cVu) + '. '
                + tint('Portfolio vs VUSA.AS', cEf) + ' is the worse, '
                + tintB(f2(effDd) + '%', cEf) + ' ('
                + (pfDd >= vuDd ? 'your portfolio' : 'VUSA.AS') + ' drives it).';
            noteEl.style.display = '';
        }

        // Episodes-on list: append each mode's current drawdown (the signal episodes are detected on),
        // calling out the gain/return so the two are not confused. Drawdown is what matters here.
        const ddLabel = (v) => (v > 0.005 ? '-' + f2(v) : '0') + '%';
        const setEp = (id, dd, color, gainHtml) =>
        {
            const e = document.getElementById(id);
            if (e) e.innerHTML = ' Currently ' + tintB(ddLabel(dd), color)
                + (gainHtml ? ', ' + gainHtml : '') + '.';
        };
        setEp('dipchart-ep-eff', effDd, cEf, '');
        setEp('dipchart-ep-ch', pfDd, cCh, 'not its ' + tint(fmtPct(cpLast), cCh) + ' return');
        setEp('dipchart-ep-vu', vuDd, cVu, 'not its ' + tint(fmtPct(vuLast), cVu) + ' gain');
    }

    // Collapsed-header summary (like /positions): "<eur> (<pct>) <label>" per metric, in metric
    // colors, separated by a middot. Shown only while the card is collapsed.
    const dipSummaryPart = (eurVal, pctVal, label, color) =>
        '<span class="text-nowrap fw-semibold" style="color:' + color + ';">'
        + fmtEur(eurVal) + ' (' + fmtPct(pctVal) + ') ' + label + '</span>';
    const dipSummaryParts = [];
    if (dipVusaMvalue !== null && vuLast !== null)
        dipSummaryParts.push(dipSummaryPart(dipVusaMvalue, vuLast, 'VUSA.AS', dipMetrics.vusa.color));
    if (chLast !== null && cpLast !== null)
        dipSummaryParts.push(dipSummaryPart(chLast, cpLast, 'change', dipMetrics.changePercentage.color));
    if (dwLast !== null)
        dipSummaryParts.push(dipSummaryPart(-ddEur, dwLast, 'Portfolio vs VUSA.AS', dipMetrics.effective.color));
    if (caLast !== null && cashPct !== null)
        dipSummaryParts.push(dipSummaryPart(caLast, cashPct, 'cash', dipMetrics.cash.color));
    $('#dipchart-summary').html(dipSummaryParts.join('<span class="text-muted">&middot;</span>'));

    // Toggle the summary with the card's collapse state, and remember that state across refreshes
    // (like /watchlist-symbols). The card defaults to open; only an explicit stored 'false' starts it
    // collapsed.
    const DIP_LS_KEY = 'dipchart_overview_expanded';
    const $dipCollapse = $('#dipchart-overview');
    const $dipToggle   = $('#dipchart-overview-title');
    const $dipSummary  = $('#dipchart-summary');
    $dipCollapse
        .on('hide.bs.collapse', function () {
            localStorage.setItem(DIP_LS_KEY, 'false');
            $dipSummary.css('display', 'flex');
        })
        .on('show.bs.collapse', function () {
            localStorage.setItem(DIP_LS_KEY, 'true');
            $dipSummary.css('display', 'none');
        })
        .on('shown.bs.collapse', function () {
            chart.resize(el.clientWidth, 250);
            renderEpisodeBands();
        });

    if (localStorage.getItem(DIP_LS_KEY) === 'false') {
        $dipCollapse.removeClass('show');
        $dipToggle.addClass('collapsed').attr('aria-expanded', 'false');
        $dipSummary.css('display', 'flex');
    }

    // Legend toggles. The line that matches the active drop mode starts visible alongside cash; the
    // other two start hidden (still toggleable from the legend).
    const dropModeMetric = { effective: 'effective', change: 'changePercentage', vusa: 'vusa' };
    const dipPrimaryMetric = dropModeMetric[dipDropMode] || 'effective';
    const visible = {};
    Object.keys(dipMetrics).forEach((metric) =>
    {
        visible[metric] = (metric === 'cash' || metric === dipPrimaryMetric);
        series[metric].applyOptions({ visible: visible[metric] });
        $('#dipchart-legend-' + metric).css('opacity', visible[metric] ? '1' : '0.3');
        $('#dipchart-legend-' + metric).on('click', function ()
        {
            visible[metric] = !visible[metric];
            series[metric].applyOptions({ visible: visible[metric] });
            $(this).css('opacity', visible[metric] ? '1' : '0.3');
        });
    });

    // Shade detected drop episodes as translucent vertical bands, repositioned on zoom/resize.
    function renderEpisodeBands()
    {
        const overlay = document.getElementById('dipchart-episode-bands');
        if (!overlay) return;
        overlay.innerHTML = '';
        const ts = chart.timeScale();
        const vr = ts.getVisibleRange();
        if (!vr) return;
        // timeToCoordinate is relative to the series pane (after the left price scale), so offset the
        // overlay (which spans the whole card) by the left axis width and clamp to the pane width.
        const leftW = chart.priceScale('left').width();
        const paneW = el.clientWidth - leftW - chart.priceScale('right').width();
        const clampX = (x) => leftW + Math.max(0, Math.min(paneW, x));
        dipEpisodes.forEach((ep) =>
        {
            // Shade only the drawdown leg (peak to trough), so each band marks the drop itself, not
            // the long grind back to recovery; a labeled line pins the low (e.g. the 2025-04-09 low).
            if (ep.low_date < vr.from || ep.peak_date > vr.to) return;
            let xPeak = ts.timeToCoordinate(ep.peak_date);
            let xLow  = ts.timeToCoordinate(ep.low_date);
            if (xPeak === null) xPeak = (ep.peak_date < vr.from) ? 0 : xLow;
            if (xLow === null)  xLow  = (ep.low_date > vr.to) ? paneW : xPeak;
            if (xPeak === null || xLow === null) return;
            const left  = clampX(Math.min(xPeak, xLow));
            const right = clampX(Math.max(xPeak, xLow));
            const band  = document.createElement('div');
            band.title = 'Drop to -' + ep.max_dd + '% (peak ' + ep.peak_date + ', low ' + ep.low_date + ')';
            band.style.cssText = 'position:absolute;top:0;bottom:0;background:rgba(220,53,69,0.10);'
                + 'border-left:1px dashed rgba(220,53,69,0.45);'
                + 'left:' + left + 'px;width:' + Math.max(1, right - left) + 'px;';
            overlay.appendChild(band);

            if (ep.low_date < vr.from || ep.low_date > vr.to) return;
            const line = document.createElement('div');
            line.style.cssText = 'position:absolute;top:0;bottom:0;width:0;'
                + 'border-left:2px solid rgba(220,53,69,0.8);left:' + right + 'px;';
            overlay.appendChild(line);
            const label = document.createElement('div');
            label.textContent = '-' + ep.max_dd + '%';
            label.style.cssText = 'position:absolute;top:2px;left:' + right + 'px;transform:translateX(-50%);'
                + 'font-size:0.7rem;color:rgba(220,53,69,0.95);background:rgba(255,255,255,0.75);'
                + 'padding:0 2px;border-radius:2px;white-space:nowrap;';
            overlay.appendChild(label);
        });

        // "Where are we now": the current drop measured from the most recent local peak. Drawn in blue
        // to set it apart from the red historical episodes (it uses a different reference point).
        if (dipCurrentDrop && dipCurrentDrop.low_date >= vr.from && dipCurrentDrop.peak_date <= vr.to)
        {
            const cd = dipCurrentDrop;
            let xPeak = ts.timeToCoordinate(cd.peak_date);
            let xLow  = ts.timeToCoordinate(cd.low_date);
            if (xPeak === null) xPeak = (cd.peak_date < vr.from) ? 0 : xLow;
            if (xLow === null)  xLow  = (cd.low_date > vr.to) ? paneW : xPeak;
            if (xPeak !== null && xLow !== null)
            {
                const left  = clampX(Math.min(xPeak, xLow));
                const right = clampX(Math.max(xPeak, xLow));
                const band  = document.createElement('div');
                band.title = 'Current drop -' + cd.current_dd + '% from local peak ' + cd.peak_date
                    + ' (deepest -' + cd.max_dd + '% on ' + cd.low_date + ')';
                band.style.cssText = 'position:absolute;top:0;bottom:0;background:rgba(13,110,253,0.10);'
                    + 'border-left:1px dashed rgba(13,110,253,0.55);'
                    + 'left:' + left + 'px;width:' + Math.max(1, right - left) + 'px;';
                overlay.appendChild(band);

                if (cd.low_date >= vr.from && cd.low_date <= vr.to)
                {
                    const line = document.createElement('div');
                    line.style.cssText = 'position:absolute;top:0;bottom:0;width:0;'
                        + 'border-left:2px solid rgba(13,110,253,0.85);left:' + right + 'px;';
                    overlay.appendChild(line);
                    const label = document.createElement('div');
                    label.textContent = 'now -' + cd.current_dd + '%';
                    label.style.cssText = 'position:absolute;top:2px;left:' + right + 'px;'
                        + 'transform:translateX(-50%);font-size:0.7rem;color:rgba(13,110,253,0.95);'
                        + 'background:rgba(255,255,255,0.8);padding:0 2px;border-radius:2px;white-space:nowrap;';
                    overlay.appendChild(label);
                }
            }
        }
    }

    function zoomChart(days)
    {
        const ts = chart.timeScale();
        if (days === 0) { ts.fitContent(); return; }
        const all = dipData.change.length ? dipData.change : dipData.changePercentage;
        if (!all.length) return;
        const last = new Date(all[all.length - 1].time + 'T00:00:00');
        const to   = new Date(last); to.setDate(to.getDate() + 5);
        const from = new Date(last); from.setDate(from.getDate() - days);
        ts.setVisibleRange({
            from: from.toISOString().split('T')[0],
            to: to.toISOString().split('T')[0],
        });
    }

    chart.timeScale().subscribeVisibleLogicalRangeChange(renderEpisodeBands);
    zoomChart(0);
    renderEpisodeBands();

    requestAnimationFrame(() =>
    {
        $('#dipchart-legend-left').css('margin-left',
            (chart.priceScale('left').width() - 2) + 'px');
        $('#dipchart-legend-right').css('margin-right', chart.priceScale('right').width() + 'px');
        renderEpisodeBands();
    });

    $('.dipchart-zoom-btn').on('click', function ()
    {
        $('.dipchart-zoom-btn').removeClass('active');
        $(this).addClass('active');
        zoomChart(parseInt($(this).data('days'), 10));
        renderEpisodeBands();
    });

    // The drop-config inputs (axis mode + threshold) re-detect episodes server-side (one source of
    // truth) on change.
    $('#dipchart-min-drop').on('change', function () { $('#dipchart-drop-form').submit(); });
    $('.dipchart-drop-option').on('click', function (e)
    {
        e.preventDefault();
        $('#dipchart-drop-mode').val($(this).data('value'));
        $('#dipchart-drop-form').submit();
    });

    $(window).on('resize', function ()
    {
        chart.resize(el.clientWidth, 250);
        renderEpisodeBands();
    });
});
</script>
