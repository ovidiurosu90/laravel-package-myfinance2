{{-- The Dip Buying Plan email's trades of the current dip, newest first: the buys and sells behind "Your actual
     (this dip)", each with its EUR amount and, for a buy, its entry drawdown below the dip's peak. Every value is
     formatted in DipBuyingPresenter::episodeTrades(); included by dip-buying-alert with $trades. --}}
@if (!empty($trades['rows']))
    <div class="label">Trades this dip</div>
    <table style="margin-bottom:6px;">
        <thead>
            <tr><th>Date</th><th>Trade</th><th class="num">Amount</th><th class="num">Entry drawdown</th></tr>
        </thead>
        <tbody>
        @foreach ($trades['rows'] as $row)
            <tr @if ($row['action'] === 'sell') class="text-muted" @endif>
                <td class="text-nowrap">{{ $row['date'] }}</td>
                <td>{{ $row['trade'] }}</td>
                <td class="num text-nowrap">{{ $row['eur'] }}</td>
                <td class="num text-nowrap">{{ $row['entry_dd'] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($trades['more'] > 0)
        <p class="text-muted" style="font-size:12px;margin-top:0;">
            And {{ $trades['more'] }} earlier {{ \Illuminate\Support\Str::plural('trade', $trades['more']) }}
            in this dip.
        </p>
    @endif
@endif
