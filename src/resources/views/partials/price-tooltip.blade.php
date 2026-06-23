@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $isPreMarket  = !empty($isPreMarket);
    $isPostMarket = !empty($isPostMarket);
    // Regular session label: "Market open" while the session is live, else "At close".
    $regularLabel = !empty($marketOpen) ? 'Market open' : 'At close';

    $formatChange = function (float $change, float $pct, string $currency)
    {
        $sign    = $change > 0 ? '+' : '';
        $pctSign = $pct > 0 ? '+' : '';
        $changeFmt = MoneyFormat::get_formatted_price_display($currency, $change);
        $pctFmt    = MoneyFormat::get_formatted_pct($pct);
        return $sign . $changeFmt . ' (' . $pctSign . $pctFmt . '%)';
    };

    $buildChangeStr = function ($change, $pct, string $currency) use ($formatChange)
    {
        return (isset($change, $pct) && ($change !== null || $pct !== null))
            ? ' ' . $formatChange((float) $change, (float) $pct, $currency)
            : '';
    };

    // Build one tooltip block (bold label + price + change, then an optional timestamp).
    $buildBlock = function (string $label, $blockPrice, $change, $pct, $ts, string $currency)
        use ($buildChangeStr)
    {
        $priceFormatted = MoneyFormat::get_formatted_price_display($currency, (float) $blockPrice, true);
        $changeStr      = $buildChangeStr($change, $pct, $currency);
        $lines = ['<strong>' . $label . ':</strong> ' . $priceFormatted . $changeStr];
        if (!empty($ts)) {
            $lines[] = $ts;
        }
        return $lines;
    };

    $blocks = [];
    if ($isPostMarket) {
        // After hours (raw delta) + At close (regular session) + Together (cumulative day).
        $blocks[] = $buildBlock('After hours', $price, $postChange ?? null, $postChangePct ?? null,
            $timestamp ?? null, $currency);
        if (!empty($regularPrice)) {
            $blocks[] = $buildBlock('At close', $regularPrice, $regularDayChange ?? null,
                $regularDayChangePct ?? null, $regularTimestamp ?? null, $currency);
        }
        $blocks[] = $buildBlock('Together', $price, $dayChange ?? null, $dayChangePct ?? null,
            null, $currency);
    } elseif ($isPreMarket) {
        // Pre-market change is already the full move vs the previous close; no cumulation.
        $blocks[] = $buildBlock('Pre-market', $price, $dayChange ?? null, $dayChangePct ?? null,
            $timestamp ?? null, $currency);
        if (!empty($regularPrice)) {
            $blocks[] = $buildBlock('At close', $regularPrice, $regularDayChange ?? null,
                $regularDayChangePct ?? null, $regularTimestamp ?? null, $currency);
        }
    } else {
        $blocks[] = $buildBlock($regularLabel, $price, $dayChange ?? null, $dayChangePct ?? null,
            $timestamp ?? null, $currency);
    }

    // Join blocks with a blank line between them.
    $tooltipLines = [];
    foreach ($blocks as $i => $block) {
        if ($i > 0) {
            $tooltipLines[] = '';
        }
        $tooltipLines = array_merge($tooltipLines, $block);
    }

    $tooltipHtml = implode('<br>', $tooltipLines);
@endphp
<span data-bs-toggle="tooltip"
      data-bs-custom-class="big-tooltips"
      data-bs-html="true"
      data-bs-title="{!! $tooltipHtml !!}">{!! $content !!}</span>
