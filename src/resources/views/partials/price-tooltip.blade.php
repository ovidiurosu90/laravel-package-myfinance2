@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $isPreMarket  = !empty($isPreMarket);
    $isPostMarket = !empty($isPostMarket);

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

    if ($isPreMarket || $isPostMarket) {
        $label            = $isPreMarket ? 'Pre-market' : 'After hours';
        $priceFormatted   = MoneyFormat::get_formatted_price_display($currency, (float) $price, true);
        $changeStr        = $buildChangeStr($dayChange ?? null, $dayChangePct ?? null, $currency);
        $tooltipLines = [
            '<strong>' . $label . ':</strong> ' . $priceFormatted . $changeStr,
            $timestamp ?? '',
        ];
        if (!empty($regularPrice)) {
            $regularFormatted  = MoneyFormat::get_formatted_price_display(
                $currency, (float) $regularPrice, true
            );
            $regularChangeStr  = $buildChangeStr(
                $regularDayChange ?? null, $regularDayChangePct ?? null, $currency
            );
            $tooltipLines[] = '';
            $tooltipLines[] = '<strong>At close:</strong> ' . $regularFormatted . $regularChangeStr;
            $tooltipLines[] = $regularTimestamp ?? '';
        }
    } else {
        $priceFormatted = MoneyFormat::get_formatted_price_display($currency, (float) $price, true);
        $changeStr      = $buildChangeStr($dayChange ?? null, $dayChangePct ?? null, $currency);
        $tooltipLines   = ['<strong>At close:</strong> ' . $priceFormatted . $changeStr, $timestamp ?? ''];
    }

    $tooltipHtml = implode('<br>', $tooltipLines);
@endphp
<span data-bs-toggle="tooltip"
      data-bs-custom-class="big-tooltips"
      data-bs-html="true"
      data-bs-title="{!! $tooltipHtml !!}">{!! $content !!}</span>
