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
                <th class="text-center no-sort no-search">Actions</th>
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
                <td class="text-center text-nowrap">
                    @if ($item->isReverted())
                        <span class="badge bg-secondary me-1"
                            data-bs-toggle="tooltip" data-bs-placement="top"
                            title="Reverted {{ $item->reverted_at->format('Y-m-d H:i') }}">
                            Reverted
                        </span>
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary js-reapply-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#reapplySplitModal"
                            data-split-id="{{ $item->id }}"
                            data-split-symbol="{{ $item->symbol }}"
                            data-split-ratio="{{ $item->getRatioLabel() }}"
                            data-split-date="{{ $item->split_date->format('Y-m-d') }}"
                            data-reapply-url="{{ route('myfinance2::stock-splits.reapply', $item->id) }}">
                            <i class="fa fa-repeat fa-fw" aria-hidden="true"></i> Reapply
                        </button>
                    @else
                        <button type="button"
                            class="btn btn-sm btn-outline-danger js-revert-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#revertSplitModal"
                            data-split-id="{{ $item->id }}"
                            data-split-symbol="{{ $item->symbol }}"
                            data-split-ratio="{{ $item->getRatioLabel() }}"
                            data-split-date="{{ $item->split_date->format('Y-m-d') }}"
                            data-trades-updated="{{ $item->trades_updated }}"
                            data-alerts-adjusted="{{ $item->alerts_adjusted }}"
                            data-revert-url="{{ route('myfinance2::stock-splits.revert', $item->id) }}"
                            data-preview-url="{{ route('myfinance2::stock-splits.revert-preview', $item->id) }}">
                            <i class="fa fa-undo fa-fw" aria-hidden="true"></i> Revert
                        </button>
                    @endif
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
                <th class="text-center"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

<div class="modal fade" id="revertSplitModal" tabindex="-1"
    aria-labelledby="revertSplitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revertSplitModalLabel">
                    <i class="fa fa-undo fa-fw" aria-hidden="true"></i> Revert Stock Split
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    Revert split <strong id="revert-modal-ratio"></strong>
                    for <strong id="revert-modal-symbol"></strong>
                    on <strong id="revert-modal-date"></strong>?
                </p>
                <div class="border rounded p-2 bg-light mb-2 small">
                    <div class="text-muted mb-1">
                        Originally updated:
                        <span class="badge bg-success" id="revert-modal-trades">0</span> trade(s),
                        <span class="badge bg-info" id="revert-modal-alerts">0</span> alert(s).
                    </div>
                    <div id="revert-preview-loading" class="text-muted">
                        <span class="spinner-border spinner-border-sm me-1" role="status"
                            aria-hidden="true"></span>
                        Checking current records&hellip;
                    </div>
                    <div id="revert-preview-result" class="d-none"></div>
                </div>
                <p class="text-muted small mb-0">
                    All trades (open and closed) and active alerts that still carry the split
                    annotation will be reverted. Alerts triggered since the split was applied
                    are not affected.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="revert-split-form" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-undo fa-fw" aria-hidden="true"></i> Confirm Revert
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reapplySplitModal" tabindex="-1"
    aria-labelledby="reapplySplitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reapplySplitModalLabel">
                    <i class="fa fa-repeat fa-fw" aria-hidden="true"></i> Reapply Stock Split
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>
                    Reapply split <strong id="reapply-modal-ratio"></strong>
                    for <strong id="reapply-modal-symbol"></strong>
                    on <strong id="reapply-modal-date"></strong>?
                </p>
                <p class="text-muted small mb-0">
                    This will update all trades (open and closed) and active alerts for this
                    symbol, and clear cached historical stats.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="reapply-split-form" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        <i class="fa fa-repeat fa-fw" aria-hidden="true"></i> Confirm Reapply
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
