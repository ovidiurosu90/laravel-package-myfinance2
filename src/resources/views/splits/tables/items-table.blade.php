<div class="table-responsive">
    <table class="table table-sm table-striped data-table split-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th>Symbol</th>
                <th>Split Date</th>
                <th class="text-center">Ratio</th>
                <th class="text-center no-search">Trades Updated</th>
                <th class="text-center no-search">Alerts Adjusted</th>
                <th class="d-none d-xl-table-cell">Notes</th>
                <th class="d-none d-xl-table-cell">Recorded At</th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if ($items->count() > 0)
            @foreach ($items as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>
                    <a href="https://finance.yahoo.com/quote/{{ $item->symbol }}"
                        target="_blank">
                        {{ $item->symbol }}
                    </a>
                </td>
                <td>{{ $item->split_date->format('Y-m-d') }}</td>
                <td class="text-center">
                    <span class="badge bg-warning text-dark">
                        {{ $item->getRatioLabel() }}
                    </span>
                </td>
                <td class="text-center">
                    @if ($item->trades_updated > 0)
                        <span class="badge bg-success">{{ $item->trades_updated }}</span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
                <td class="text-center">
                    @if ($item->alerts_adjusted > 0)
                        <span class="badge bg-info">{{ $item->alerts_adjusted }}</span>
                    @else
                        <span class="text-muted">0</span>
                    @endif
                </td>
                <td class="d-none d-xl-table-cell" style="white-space: normal;">
                    <span class="small text-muted">{{ $item->notes ?: '—' }}</span>
                </td>
                <td class="d-none d-xl-table-cell text-nowrap">
                    {{ $item->created_at->format('Y-m-d H:i') }}
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th>Id</th>
                <th>Symbol</th>
                <th>Split Date</th>
                <th class="text-center">Ratio</th>
                <th class="text-center"></th>
                <th class="text-center"></th>
                <th class="d-none d-xl-table-cell">Notes</th>
                <th class="d-none d-xl-table-cell">Recorded At</th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>
