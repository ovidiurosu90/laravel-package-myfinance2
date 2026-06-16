@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    // Renders the quote header shown at the top of the symbol chart modal:
    // an "At close" block plus, during extended hours, a "Pre-market" /
    // "After hours" block. Each block shows a large price and a green/red
    // change (amount and %), with a label + timestamp underneath.
    $qh_isPre    = !empty($isPreMarket);
    $qh_isPost   = !empty($isPostMarket);
    $qh_extended = $qh_isPre || $qh_isPost;

    // Regular session label: "Market open" while the session is live, else "At close".
    $qh_regularLabel = !empty($marketOpen) ? 'Market open' : 'At close';

    $qh_blocks = [];

    // Regular session ("At close"). When in extended hours the live figure is
    // the pre/post one, so the regular session uses the regular_* fields.
    if ($qh_extended && isset($regularPrice) && $regularPrice !== null) {
        $qh_blocks[] = [
            'label'     => 'At close',
            'price'     => $regularPrice,
            'change'    => $regularDayChange ?? null,
            'changePct' => $regularDayChangePct ?? null,
            'timestamp' => $regularTimestamp ?? null,
        ];
    } else {
        $qh_blocks[] = [
            'label'     => $qh_regularLabel,
            'price'     => $price ?? null,
            'change'    => $dayChange ?? null,
            'changePct' => $dayChangePct ?? null,
            'timestamp' => $timestamp ?? null,
        ];
    }

    if ($qh_extended) {
        $qh_blocks[] = [
            'label'     => $qh_isPre ? 'Pre-market' : 'After hours',
            'price'     => $price ?? null,
            'change'    => $dayChange ?? null,
            'changePct' => $dayChangePct ?? null,
            'timestamp' => $timestamp ?? null,
        ];
    }
@endphp
<div class="d-flex flex-wrap justify-content-start align-items-start gap-4">
@foreach($qh_blocks as $qh_block)
@continue($qh_block['price'] === null)
    <div class="d-inline-flex flex-column align-items-start">
        <div class="d-flex align-items-baseline gap-2">
            <span class="fs-4 fw-bold">{!!
                MoneyFormat::get_formatted_price_display(
                    $currency, (float) $qh_block['price'], true
                )
            !!}</span>
            @if($qh_block['change'] !== null)
            <span class="fw-semibold">{!!
                MoneyFormat::get_formatted_gain($currency, (float) $qh_block['change'])
            !!}@if($qh_block['changePct'] !== null) ({!!
                MoneyFormat::get_formatted_gain_percentage((float) $qh_block['changePct'])
            !!})@endif</span>
            @endif
        </div>
        <div class="small text-muted">
            {{ $qh_block['label'] }}@if(!empty($qh_block['timestamp'])): {{ $qh_block['timestamp'] }}@endif
        </div>
    </div>
@endforeach
</div>
