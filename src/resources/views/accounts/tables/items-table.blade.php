<div class="table-responsive">
    <table class="table table-sm table-striped data-table account-items-table">
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">{{ trans(
                    'myfinance2::accounts.forms.item-form.currency.label') }}
                </th>
                <th scope="col">{{ trans(
                    'myfinance2::accounts.forms.item-form.name.label') }}
                </th>
                <th scope="col">{{ trans(
                    'myfinance2::accounts.forms.item-form.description.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::accounts.forms.item-form.'
                                         . 'is_ledger_account.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::accounts.forms.item-form.'
                                         . 'is_trade_account.label') }}
                </th>
                <th scope="col">{{ trans('myfinance2::accounts.forms.item-form.'
                                         . 'is_dividend_account.label') }}
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
                <td>{{ $item->currency->name }}
                    ({!! $item->currency->display_code !!})</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->is_ledger_account }}</td>
                <td>{{ $item->is_trade_account }}</td>
                <td>{{ $item->is_dividend_account }}</td>
                <td>{{ $item->created_at }}</td>
                <td>{{ $item->updated_at }}</td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary btn-block"
                       href="{{ route('myfinance2::accounts.edit',
                                      $item->id) }}"
                       data-bs-toggle="tooltip"
                       title="{{ trans('myfinance2::general.tooltips.edit-item',
                                       ['type' => 'Account']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::accounts.forms.delete-sm',
                             ['type' => 'Account', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

