{{-- The superstar star: Platinum or Gold under most of the 7 tier approaches (TierApproachComparison::superstar()),
     shown after the symbol in the approach comparison and the "Holdings by tier" tables, after the tier badges in the
     table's Tier column and the peak-proximity inbox cards, and before "Tier" on the watchlist row (the alert email
     shows a gold &#9733; after the tier). A gold icon, so it does not read as the text ★ that marks a manual tier
     override inside a badge. --}}
@if(!empty($superstar))
    <i class="fas fa-star text-warning" style="font-size:0.85em;" aria-hidden="true" data-bs-toggle="tooltip"
        data-bs-custom-class="tooltip-wide" title="{{ $superstar['title'] }}"></i>
@endif
