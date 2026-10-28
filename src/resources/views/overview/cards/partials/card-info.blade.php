{{-- The "i" icon next to an overview card's title: a hover explaining the card. $card: the card's key under
     myfinance2::overview.cards (its "info" text holds HTML). --}}
<i class="fa fa-info-circle text-muted ms-1" aria-hidden="true" data-bs-toggle="tooltip" data-bs-html="true"
    data-bs-placement="bottom" data-bs-custom-class="tooltip-wide"
    title="{{ trans('myfinance2::overview.cards.' . $card . '.info') }}"></i>
