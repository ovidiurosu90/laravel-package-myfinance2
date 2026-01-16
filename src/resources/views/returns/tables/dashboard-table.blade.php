
{{-- Year Selector & Currency Toggle --}}
<div class="card mb-4">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <label for="year-selector" class="mb-0">
                    {{ trans('myfinance2::returns.titles.year-selector') }}
                </label>
                <select id="year-selector" class="form-select" style="width: auto;">
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ $year == $selectedYear ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; align-items: center; gap: 2rem; flex: 1;">
                <div style="text-align: center; flex: 1;">
                    <div class="currency-value fw-bold" style="font-size: 1.5rem;"
                        data-eur="{{ $totalReturnEURFormatted }}"
                        data-usd="{{ $totalReturnUSDFormatted }}"
                        data-eur-value="{{ $totalReturnEUR }}"
                        data-usd-value="{{ $totalReturnUSD }}"
                        data-bs-toggle="tooltip"
                        data-bs-placement="bottom"
                        data-bs-title="Total Return = sum of returns of all the accounts for the selected year">
                        {!! $totalReturnSelectedColored !!}
                    </div>
                </div>
                <div style="flex: 1; text-align: center;">
                    <small style="color: #0c63e4;">
                        {!! trans('myfinance2::returns.labels.formula-explanation') !!}
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-stretch gap-3">
                <input id="toggle-currency-select" type="checkbox" {{ $selectedCurrency === 'EUR' ? 'checked' : '' }}
                    data-bs-toggle="toggle"
                    data-onlabel="Euro (&euro;)"
                    data-offlabel="US Dollar (&dollar;)" />
                <form action="{{ route('myfinance2::returns.clear-cache') }}" method="POST" class="m-0">
                    @csrf
                    <input type="hidden" name="year" value="{{ $selectedYear }}">
                    @php
                        $clearCacheMsg = 'Are you sure you want to clear the returns cache? '
                            . 'The page will recalculate data on next load, which may take a moment.';
                    @endphp
                    <button type="button" class="btn btn-outline-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#confirm-clear-cache-modal"
                        data-title="Clear Returns Cache"
                        data-message="{{ $clearCacheMsg }}"
                        title="Clear the returns cache to force recalculation">
                        <i class="fa-solid fa-trash-can me-1"></i>Clear Cache
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Returns Data for Each Account --}}
@foreach($returnsData as $accountId => $data)
<div class="card mb-4">
    <div class="card-header" style="cursor: pointer;"
        data-bs-toggle="collapse"
        data-bs-target="#account-{{ $accountId }}">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>
                {{ $data['account']->name }} - Returns for {{ $selectedYear }}
                @if($selectedYear == date('Y'))
                    <small class="text-muted">(Year-to-date)</small>
                @endif
            </span>
            @php
                $actualReturnValue = $selectedCurrency === 'EUR'
                    ? $data['actualReturnColored']['EUR']['formatted']
                    : $data['actualReturnColored']['USD']['formatted'];
            @endphp
            <span class="fw-bold currency-value"
                  data-eur="{{ $data['actualReturn']['EUR']['plain'] }}"
                  data-usd="{{ $data['actualReturn']['USD']['plain'] }}"
                  data-eur-value="{{ $data['actualReturn']['EUR']['value'] }}"
                  data-usd-value="{{ $data['actualReturn']['USD']['value'] }}">
                {!! $actualReturnValue !!}
            </span>
        </div>
    </div>
    <div class="card-body collapse" id="account-{{ $accountId }}">
        <table class="table table-bordered">
            <tbody>
                @include('myfinance2::returns.tables.dividends')

                @include('myfinance2::returns.tables.end-value')

                @include('myfinance2::returns.tables.start-value')

                @include('myfinance2::returns.tables.deposits')

                @include('myfinance2::returns.tables.withdrawals')

                @include('myfinance2::returns.tables.purchases')

                @include('myfinance2::returns.tables.sales')

                {{-- Actual Return --}}
                <tr class="table-primary">
                    <td class="fw-bold">{{ trans('myfinance2::returns.labels.actual-return') }}</td>
                    <td class="fw-bold currency-value"
                        data-eur="{{ $data['actualReturn']['EUR']['plain'] }}"
                        data-usd="{{ $data['actualReturn']['USD']['plain'] }}"
                        data-eur-value="{{ $data['actualReturn']['EUR']['value'] }}"
                        data-usd-value="{{ $data['actualReturn']['USD']['value'] }}"
                        @if(isset($data['actualReturnOverride']['EUR']))
                            data-eur-override="{{ $data['actualReturnOverride']['EUR']['overrideFormatted'] }}"
                            data-eur-calculated="{{ $data['actualReturnOverride']['EUR']['calculatedFormatted'] }}"
                        @endif
                        @if(isset($data['actualReturnOverride']['USD']))
                            data-usd-override="{{ $data['actualReturnOverride']['USD']['overrideFormatted'] }}"
                            data-usd-calculated="{{ $data['actualReturnOverride']['USD']['calculatedFormatted'] }}"
                        @endif>
                        @php
                            $returnValue = $selectedCurrency === 'EUR'
                                ? $data['actualReturnColored']['EUR']['formatted']
                                : $data['actualReturnColored']['USD']['formatted'];
                        @endphp
                        <span data-bs-toggle="tooltip" data-bs-placement="top">
                            {!! $returnValue !!}
                        </span>
                        @if($selectedCurrency === 'EUR' && isset($data['actualReturnOverride']['EUR']))
                            @php
                                $overrideMsg = 'This return has been overridden. '
                                    . ($data['actualReturnOverride']['reason'] ?? 'See configuration for details.');
                            @endphp
                            <i class="fa-solid fa-circle-info ms-1 return-override-icon"
                                style="font-size: 0.75rem; color: black;"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="{{ $overrideMsg }}"></i>
                            <small style="color: #6c757d; margin-left: 0.5rem;" class="return-calculated-value">
                                (Calculated: {!! $data['actualReturnOverride']['EUR']['calculatedFormatted'] !!})
                            </small>
                        @elseif($selectedCurrency === 'USD' && isset($data['actualReturnOverride']['USD']))
                            @php
                                $overrideMsg = 'This return has been overridden. '
                                    . ($data['actualReturnOverride']['reason'] ?? 'See configuration for details.');
                            @endphp
                            <i class="fa-solid fa-circle-info ms-1 return-override-icon"
                                style="font-size: 0.75rem; color: black;"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="{{ $overrideMsg }}"></i>
                            <small style="color: #6c757d; margin-left: 0.5rem;" class="return-calculated-value">
                                (Calculated: {!! $data['actualReturnOverride']['USD']['calculatedFormatted'] !!})
                            </small>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endforeach

@if(count($returnsData) === 0)
<div class="alert alert-warning">
    <strong>No trading accounts found.</strong> Please create a trading account to view returns.
</div>
@endif
