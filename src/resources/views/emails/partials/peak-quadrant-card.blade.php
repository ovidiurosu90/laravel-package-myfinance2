{{-- The Quadrant card of the peak-proximity alert: the tier and its basis, then the per-window market reading.
     Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if ($quadrant)
<div class="section" style="border-left-color:#6f42c1;">
    <h2>Quadrant</h2>
    <div style="margin-bottom:8px;">
        <span class="text-muted">Tier:</span>
        <span class="badge {{ $quadrant['tier_class'] }}">{{ $quadrant['tier_label'] }}</span>
        @if ($quadrant['basis_val'] !== null)
        <span class="text-muted">&middot; tier basis</span>
        @if ($quadrant['gain_eur'] !== null)
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain($eur, $quadrant['gain_eur']) !!}</span>
        @endif
        (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['basis_val']) !!}</span>)
        @endif
        @if ($quadrant['action'] !== null)
        &middot; <span class="badge {{ $quadrant['action_class'] }}">{{ $quadrant['action'] }}</span>
        @endif
    </div>
    @if ($quadrant['xirr_pct'] !== null || $quadrant['alpha_pct'] !== null)
    <div style="margin-bottom:8px;" class="text-muted">
        @if ($quadrant['xirr_pct'] !== null)
        Money-weighted XIRR
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['xirr_pct']) !!}/y</span>
        @endif
        @if ($quadrant['alpha_pct'] !== null)
        &middot; vs <a class="sym-link" href="{{ $vusaUrl }}" target="_blank">VUSA.AS</a>
        <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $quadrant['alpha_pct']) !!}</span>
        @endif
    </div>
    @endif

    @if (!empty($quadrant['rows']))
    <table>
        <tr>
            <th></th>
            <th>Tier</th>
            <th>Quadrant</th>
            <th class="text-nowrap">Market return</th>
            <th>Risk</th>
            <th>Action</th>
            <th class="text-nowrap">From peak</th>
            <th>P&amp;L at peak</th>
        </tr>
        @foreach ($quadrant['rows'] as $row)
        <tr @if ($row['near']) class="near-peak" @endif>
            <td class="text-muted text-nowrap">
                {{ $row['label'] }}
                @if ($row['near'])<span class="badge bg-success">near peak</span>@endif
            </td>
            <td>
                @if ($row['tier_label'] !== null)
                <span class="badge {{ $row['tier_class'] }}">{{ $row['tier_label'] }}</span>
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td>
                @if ($row['quadrant_label'] !== null)
                <span class="badge {{ $row['quadrant_class'] }}">{{ $row['quadrant_label'] }}</span>
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td>
                @if ($row['gain'] !== null)
                {!! MoneyFormat::get_formatted_gain('%', $row['gain']) !!}
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td class="text-nowrap">
                @if ($row['risk'] !== null)
                {{ MoneyFormat::get_formatted_number_plain($row['risk'], 2) }}x
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td>
                @if ($row['action'] !== null)
                <span class="badge {{ $row['action_class'] }}">{{ $row['action'] }}</span>
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td class="text-nowrap">
                @if ($row['prox'] !== null)
                {!! MoneyFormat::get_formatted_gain('%', $row['prox']) !!}
                <span class="text-muted">(peak {{ $row['peak_date'] ?? 'n/a' }})</span>
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
            <td>
                @if ($row['pnl_eur'] !== null)
                {!! MoneyFormat::get_formatted_gain($eur, $row['pnl_eur']) !!}
                @if ($row['pnl_pct'] !== null)
                ({!! MoneyFormat::get_formatted_gain('%', $row['pnl_pct']) !!})
                @endif
                @else
                <span class="text-muted">-</span>
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    @endif
</div>
@endif
