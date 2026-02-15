@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@php
    $hasOther = count($otherAccounts) > 0;
    $hasUncategorized = count($uncategorizedAccounts) > 0;
    $hasIntermediary = count($intermediaryAccounts) > 0;
    $hasAny = $hasOther || $hasUncategorized || $hasIntermediary;
    $allOtherAccounts = array_merge(
        $intermediaryAccounts,
        $otherAccounts,
        $uncategorizedAccounts
    );
    $otherCount = count($allOtherAccounts);
@endphp

@if($hasAny)
<div class="col-12">
    <div class="card">
        <div class="card-header" style="cursor: pointer;"
             data-bs-toggle="collapse"
             data-bs-target="#otherAccountsBody"
             aria-expanded="false"
             aria-controls="otherAccountsBody">
            <span id="card_title">
                <i class="fa-solid fa-chevron-right me-1
                          other-acc-chevron"
                    style="font-size: 0.7rem;
                           transition: transform 0.2s;">
                </i>
                {!! trans('myfinance2::overview.cards.'
                          . 'other-accounts.title') !!}
                ({{ $otherCount }})
            </span>
        </div>
        <div class="collapse" id="otherAccountsBody">
            <div class="card-body p-0">
                <div class="list-group-flush flex-fill">
                    <table class="table table-sm table-striped
                                  mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Account</th>
                                <th scope="col">Description</th>
                                <th scope="col" class="text-right">
                                    Balance
                                </th>
                                <th scope="col">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($allOtherAccounts as $accountId => $data)
                            <tr>
                                <td>
                                    {{ $data['account']->name }}
                                    ({!! $data['account']->currency
                                                         ->display_code !!})
                                </td>
                                <td class="text-muted">
                                    {{ $data['account']->description }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {!! MoneyFormat::get_formatted_gain(
                                            $data['account']->currency
                                                            ->display_code,
                                            $data['balance']) !!}
                                </td>
                                <td class="text-muted">
                                    @if($data['account']->funding_role)
                                        {{ trans(
                                            'myfinance2::accounts.forms.'
                                            . 'item-form.funding_role'
                                            . '.options.'
                                            . $data['account']
                                                  ->funding_role->value
                                        ) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module">
    const header = document.querySelector(
        '[data-bs-target="#otherAccountsBody"]'
    );
    if (header) {
        const chevron = header.querySelector('.other-acc-chevron');
        const collapseEl = document.getElementById(
            'otherAccountsBody'
        );
        collapseEl.addEventListener('show.bs.collapse', () => {
            chevron.style.transform = 'rotate(90deg)';
        });
        collapseEl.addEventListener('hide.bs.collapse', () => {
            chevron.style.transform = '';
        });
    }
</script>
@endif
