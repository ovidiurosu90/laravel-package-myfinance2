{{-- How to read the portfolio peak alert's proximity table: the columns, the Fires? badges and the EUR-gain floor.
     Included by portfolio-peak-alert, whose variables it reads ($badge, $minPeakAbs, $hasPctRow). --}}
<p class="text-muted" style="font-size:12px;margin:8px 0 4px;">
    Every window is shown, not only the ones that would fire.
</p>
<p class="text-muted" style="font-size:12px;margin:0 0 4px;">
    "From peak" is how far the current value sits below that window's high (always a negative
    drawdown; 0 % means at the high); "Threshold" is how close it must get to fire.
</p>
<ul class="text-muted" style="font-size:12px;margin:0 0 4px;padding-left:18px;">
    <li style="margin-bottom:4px;">{!! $badge('yes', '#28a745', '#fff') !!} the window is inside its threshold now.</li>
    <li style="margin-bottom:4px;">{!! $badge('no', '#6c757d', '#fff') !!} a gating window still outside it.</li>
    <li style="margin-bottom:4px;">
        {!! $badge('context', '#f8f9fa', '#222', true) !!} the 3M window (shown for awareness,
        never fires on its own).
    </li>
    <li style="margin-bottom:4px;">
        {!! $badge('n/a', '#f8f9fa', '#6c757d', true) !!} a EUR-gain window whose peak sits within
        &plusmn;&nbsp;{{ $minPeakAbs }} of zero, i.e. its magnitude |peak| is under the min_peak_abs_eur floor, too
        small to measure proximity against without flipping on noise. A large negative peak is
        <strong>not</strong> skipped.
    </li>
</ul>
@if ($hasPctRow)
    <p class="text-muted" style="font-size:12px;margin:0 0 4px;">
        For Return % rows the value columns show the raw return on cost, while From peak is the
        value-index proximity (1 + return/100), so it stays sane even when the whole window is
        underwater.
    </p>
@endif
<p class="text-muted" style="font-size:12px;margin:0;">
    EUR-gain proximity is measured against the magnitude of each window's peak (|peak|, ignoring
    sign), so only a peak within &plusmn;&nbsp;{{ $minPeakAbs }} of zero is skipped.
    A deeply negative peak (for example - 50,000 €) is in scope: the alert fires when your
    current EUR gain climbs back to within the window's threshold of it.
</p>
