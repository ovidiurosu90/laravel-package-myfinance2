{{ csrf_field() }}
<div class="row">
    <div class="col-12 col-md-4">
        @include('myfinance2::splits.forms.partials.symbol-select')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::splits.forms.partials.split_date-picker')
    </div>
    <div class="col-12 col-md-4">
        @include('myfinance2::splits.forms.partials.ratio-inputs')
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        <div class="card border-warning mb-0">
            <div class="card-header bg-warning text-dark py-2 small fw-semibold">
                <i class="fa fa-fw fa-list" aria-hidden="true"></i>
                Open Trades — will be updated on save
            </div>
            <div class="card-body py-2">
                <div id="open-trades-pre-select" class="text-muted small py-1">
                    <i class="fa fa-fw fa-arrow-up" aria-hidden="true"></i>
                    Select a symbol above to preview the trades that will be updated.
                </div>
                <div id="open-trades-loading" class="text-muted small py-1" style="display:none">
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Loading trades...
                </div>
                <div id="open-trades-content" style="display:none">
                    <div id="open-trades-empty" class="text-muted small py-1" style="display:none">
                        No open trades for this symbol match the selected date.
                    </div>
                    <div id="open-trades-table-wrap" style="display:none">
                        <table class="table table-sm table-borderless mb-0">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Account</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                </tr>
                            </thead>
                            <tbody id="open-trades-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @include('myfinance2::splits.forms.partials.notes-input')
    </div>
</div>
