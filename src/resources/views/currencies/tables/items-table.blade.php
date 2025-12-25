<div class="table-responsive">
    <table class="table table-sm table-striped data-table currency-items-table">
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">{{ trans(
                    'myfinance2::currencies.forms.item-form.iso_code.label') }}
                </th>
                <th scope="col">{{ trans(
                    'myfinance2::currencies.forms.item-form.display_code.label') }}
                </th>
                <th scope="col">{{ trans(
                    'myfinance2::currencies.forms.item-form.name.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::currencies.forms.item-form.'
                                         . 'is_ledger_currency.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::currencies.forms.item-form.'
                                         . 'is_trade_currency.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::currencies.forms.item-form.'
                                         . 'is_dividend_currency.label') }}
                </th>
                <th scope="col" class="hidden-xs hidden-sm">Created</th>
                <th scope="col" class="hidden-xs hidden-sm">Updated</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if( $items->count() > 0)
            @foreach($items as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->iso_code }}</td>
                <td>{!! $item->display_code !!}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->is_ledger_currency }}</td>
                <td>{{ $item->is_trade_currency }}</td>
                <td>{{ $item->is_dividend_currency }}</td>
                <td>{{ $item->created_at }}</td>
                <td>{{ $item->updated_at }}</td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                       href="{{ route('myfinance2::currencies.edit',
                                      $item->id) }}"
                       data-bs-toggle="tooltip"
                       title="{{ trans('myfinance2::general.tooltips.edit-item',
                                       ['type' => 'Currency']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::currencies.forms.delete-sm',
                             ['type' => 'Currency', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

