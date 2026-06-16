@use('ovidiuro\myfinance2\App\Services\DipBuyingBacktestService')
@php
    // Self-contained card. Expects $dipChart (from the controller via
    // DipBuyingBacktestService::chartContext): ['min_drop' => float, 'episodes' => [...]]. The script
    // half lives in scripts/drawdown-chart.blade.php (include it in footer_scripts on the same page).
    $dipMetrics     = DipBuyingBacktestService::chartMetrics();
    $dropModes      = DipBuyingBacktestService::dropModes();
    $minDropDisplay = (float) $dipChart['min_drop'];
    $dropMode       = $dipChart['drop_mode'] ?? 'effective';
    // Tint each "Episodes on" option with its line color (same metric, same axis legend color).
    $dropModeColors = [
        'effective' => $dipMetrics['effective']['color'],
        'change'    => $dipMetrics['changePercentage']['color'],
        'vusa'      => $dipMetrics['vusa']['color'],
    ];
    // Shortcuts for tinting the metric names in the "How to read this chart" notes.
    $cEffective = $dipMetrics['effective']['color'];
    $cChange    = $dipMetrics['changePercentage']['color'];
    $cVusa      = $dipMetrics['vusa']['color'];
@endphp
<style>
    /* Hide the number input spinner arrows to keep the drop-config form compact. */
    .dipchart-no-spin::-webkit-outer-spin-button,
    .dipchart-no-spin::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .dipchart-no-spin { -moz-appearance: textfield; appearance: textfield; }
</style>
<div class="card card-default">
    <div class="card-header d-flex align-items-center gap-3">
        <span class="text-nowrap">Drawdown overview</span>
        {{-- Populated by scripts/drawdown-chart.blade.php; shown only while the card is collapsed. --}}
        <div id="dipchart-summary"
             class="align-items-center gap-2 flex-grow-1 overflow-hidden justify-content-end"
             style="display:none;"></div>
        <a id="dipchart-overview-title" class="btn btn-sm ms-auto flex-shrink-0" href="#dipchart-overview"
            aria-expanded="true" aria-controls="dipchart-overview"
            data-bs-toggle="collapse" title="Collapse">
            <i class="fa fa-chevron-down pull-right"></i>
        </a>
    </div>
    <div id="dipchart-overview" class="collapse show" aria-labelledby="dipchart-overview-title">
    <div class="card-body">
        <div class="position-relative">
            {{-- Controls strip: the form takes only the width it needs (flex-shrink-0, no wrap); the
                 four metric sections share the rest (flex-fill). Bars are pinned to the bottom of each
                 equal-height cell with mt-auto so they line up across sections. --}}
            <div class="d-flex align-items-stretch">
                <div class="d-flex flex-column flex-shrink-0 pr-3 text-nowrap">
                    <form method="GET" id="dipchart-drop-form" class="mb-0 d-flex align-items-center gap-2">
                        @if (request('from'))<input type="hidden" name="from" value="{{ request('from') }}">@endif
                        @if (request('pool'))<input type="hidden" name="pool" value="{{ request('pool') }}">@endif
                        {{-- Native <option> colors are ignored on some platforms (Linux/GTK render the
                             menu natively), so use a Bootstrap dropdown whose items respect CSS. The
                             chosen value is carried in a hidden input the form already submits. --}}
                        <input type="hidden" name="drop_mode" id="dipchart-drop-mode" value="{{ $dropMode }}">
                        <div class="dropdown" data-bs-toggle="tooltip"
                             title="Which basis the shaded drop episodes are detected on (the red line stays effective)">
                            <button class="btn btn-sm dropdown-toggle d-flex justify-content-between align-items-center"
                                    type="button" id="dipchart-drop-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                                    style="color: {{ $dropModeColors[$dropMode] ?? 'inherit' }};
                                           border:1px solid #ced4da; background:#fff; min-width:11rem;">
                                {{ $dropModes[$dropMode] ?? $dropMode }}
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dipchart-drop-toggle">
                                @foreach ($dropModes as $value => $label)
                                <li>
                                    <a class="dropdown-item dipchart-drop-option" href="#"
                                       data-value="{{ $value }}"
                                       style="color: {{ $dropModeColors[$value] ?? 'inherit' }};">
                                        {{ $label }}
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="input-group input-group-sm w-auto">
                            <span class="input-group-text">&ge;</span>
                            <input type="number" step="0.5" min="1" max="50"
                                   class="form-control form-control-sm dipchart-no-spin" id="dipchart-min-drop"
                                   name="min_drop" value="{{ $minDropDisplay }}"
                                   style="flex:0 0 auto;width:2.5rem;"
                                   data-bs-toggle="tooltip"
                                   title="Minimum drawdown on the selected basis that counts as an episode">
                            <span class="input-group-text">%</span>
                        </div>
                    </form>
                    <div class="btn-group w-100 mt-auto pt-1" role="group" style="font-size:0.6rem;line-height:1.2;">
                        <button type="button" class="btn btn-outline-secondary dipchart-zoom-btn flex-fill py-0 px-0"
                                data-days="30">1M</button>
                        <button type="button" class="btn btn-outline-secondary dipchart-zoom-btn flex-fill py-0 px-0"
                                data-days="182">6M</button>
                        <button type="button" class="btn btn-outline-secondary dipchart-zoom-btn flex-fill py-0 px-0"
                                data-days="365">1Y</button>
                        <button type="button" class="btn btn-outline-secondary dipchart-zoom-btn active flex-fill py-0 px-0"
                                data-days="0">ALL</button>
                    </div>
                </div>
                @foreach (['vusa', 'change', 'drawdown', 'cash'] as $m)
                <div class="d-flex flex-column flex-fill px-2">
                    <div class="pt-1 fs-5 fw-bold text-center"><span id="dipchart-{{ $m }}-status"></span></div>
                    <div class="mt-auto"><div id="dipchart-{{ $m }}-range-bar"></div></div>
                </div>
                @endforeach
            </div>
            <div class="position-relative" style="margin-top:36px;">
                <div id="chart-dipDrawdown"></div>
                <div id="dipchart-episode-bands"
                     style="position:absolute;inset:0;z-index:5;pointer-events:none;"></div>
                <div style="position:absolute;top:-26px;left:0;right:0;z-index:10;display:flex;
                    justify-content:space-between;align-items:flex-start;pointer-events:none;">
                    <div id="dipchart-legend-left" style="display:flex;gap:6px;pointer-events:none;">
                        @foreach (['cash'] as $m)
                        <span id="dipchart-legend-{{ $m }}"
                            style="cursor:pointer;pointer-events:auto;
                                border:2px {{ $dipMetrics[$m]['border'] }} {{ $dipMetrics[$m]['color'] }};
                                color:{{ $dipMetrics[$m]['color'] }};border-radius:4px;padding:2px 8px;
                                font-size:0.75rem;user-select:none;background:rgba(255,255,255,0.8);">
                            {{ $dipMetrics[$m]['title'] }}
                        </span>
                        @endforeach
                    </div>
                    <div id="dipchart-legend-right" style="display:flex;gap:6px;pointer-events:none;">
                        @foreach (['effective', 'changePercentage', 'vusa'] as $m)
                        <span id="dipchart-legend-{{ $m }}"
                            style="cursor:pointer;pointer-events:auto;
                                border:2px {{ $dipMetrics[$m]['border'] }} {{ $dipMetrics[$m]['color'] }};
                                color:{{ $dipMetrics[$m]['color'] }};border-radius:4px;padding:2px 8px;
                                font-size:0.75rem;user-select:none;background:rgba(255,255,255,0.8);">
                            {{ $dipMetrics[$m]['title'] }}
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="small text-muted mt-3">
                <ul class="mb-0 ps-3">
                    <li>
                        <span class="text-dark">Drawdown</span> is how far below its highest point so far
                        something sits: 0% at a new high, &minus;23% when you are 23% below the peak.
                    </li>
                    <li>
                        The <span style="color:{{ $cEffective }};">Portfolio vs VUSA.AS</span> line is the
                        <span class="text-dark">effective drawdown</span>: the worse of your own portfolio's
                        drawdown and <span style="color:{{ $cVusa }};">VUSA.AS</span>'s, at each date. It is
                        always shown, whatever <span class="text-dark">Episodes on</span> is set to.
                    </li>
                    {{-- Live worked example connecting the top bars to the effective drawdown; filled by
                         scripts/drawdown-chart.blade.php from the same numbers the bars use. Each metric's
                         drawdown is how far its current marker sits below its own peak (the bar's max). --}}
                    <li id="dipchart-worse-note" style="display:none;"></li>
                    <li>
                        <span class="text-dark">Episodes on</span> chooses which drawdown the shaded drop
                        windows are detected on:
                        <ul class="mb-0">
                            <li><span style="color:{{ $cEffective }};">Portfolio vs VUSA.AS</span>: the worse of the two (the effective drawdown).<span id="dipchart-ep-eff"></span></li>
                            <li><span style="color:{{ $cChange }};">Change %</span>: your portfolio's own drawdown only.<span id="dipchart-ep-ch"></span></li>
                            <li><span style="color:{{ $cVusa }};">VUSA.AS</span>: the benchmark's drawdown only.<span id="dipchart-ep-vu"></span></li>
                        </ul>
                    </li>
                    <li>
                        Each <span class="text-dark">shaded band</span> is a drop episode: a stretch where the
                        chosen drawdown fell at least {{ $minDropDisplay }}% below a peak. It is shaded from the
                        peak to the trough (the lowest point), the dashed red line marks the
                        {{ $minDropDisplay }}% enter cutoff, and the vertical line pins the low.
                        {{ count($dipChart['episodes']) }} found in this window.
                    </li>
                    @if (!empty($dipChart['current_drop']))
                    <li>
                        The <span style="color:rgba(13,110,253,1);">blue band</span> shows where you are right
                        now: <span style="color:rgba(13,110,253,1);">down {{ (float) $dipChart['current_drop']['current_dd'] }}%
                        now</span> from the most recent <span class="text-dark">local peak</span>. It starts at
                        that recent peak, not the all-time high the episodes use, so it is not directly
                        comparable to them.
                    </li>
                    @endif
                    <li>
                        The euro figure above the <span style="color:{{ $cEffective }};">Portfolio vs VUSA.AS</span>
                        bar is what that drawdown is worth on
                        your portfolio today: current market value &times; f &divide; (1 &minus; f), where f is
                        the current effective drawdown as a fraction. It is 0 when you are at a new high.
                    </li>
                </ul>
            </div>
        </div>
    </div>
    </div>
</div>
