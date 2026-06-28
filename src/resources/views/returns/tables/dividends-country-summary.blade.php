{{-- Dutch Tax Return - Dividend Summary by Country --}}
{{-- Included from dividends.blade.php inside a Bootstrap row --}}
@php $cs = $data['dividendsSummaryByCountry']; @endphp


{{-- Section A: Full country breakdown --}}
<div class="col-12 col-lg-4">
    <strong style="font-size: 0.95rem;">Dutch Tax Return - Dividend Breakdown</strong>
    <i class="fa fa-info-circle"
        style="margin-left: 0.25rem; font-size: 0.8rem; color: #6c757d;"
        data-bs-toggle="tooltip"
        data-bs-placement="top"
        data-bs-title="Amounts may differ slightly from your broker yearly statement due to exchange rate rounding. Always verify against the official broker statement before filing."></i>
    <table class="table table-sm table-striped mb-0" style="margin-top: 0.75rem;">
        <thead>
            <tr class="small">
                <th style="width: 15%;">Country</th>
                <th class="text-end" style="width: 28.33%;">Gross Dividend</th>
                <th class="text-end" style="width: 28.33%;">Withholding Tax</th>
                <th class="text-end" style="width: 28.33%;">Net Dividend</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cs['byCountry'] as $row)
            <tr class="small">
                <td class="fw-bold">
                    {{ $row['country'] }}
                    @if(!empty($row['symbols']))
                    <i class="fa fa-info-circle"
                        style="font-size: 0.75rem; color: #6c757d;"
                        data-bs-toggle="tooltip"
                        data-bs-placement="right"
                        data-bs-title="{{ implode(', ', $row['symbols']) }}"></i>
                    @endif
                </td>
                <td class="text-end text-nowrap">{!! $row['grossFormatted'] !!}</td>
                <td class="text-end text-nowrap">
                    @if($row['taxFormatted'])
                        {!! $row['taxFormatted'] !!}
                    @else
                        <i class="fa fa-info-circle"
                            style="font-size: 0.75rem; color: #6c757d;"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-custom-class="tooltip-md"
                            data-bs-title="No withholding tax applies for this country. This is configured via dividend_withholding_tax_countries in trades.php."></i>
                    @endif
                </td>
                <td class="text-end text-nowrap">{!! $row['netFormatted'] !!}</td>
            </tr>
            @endforeach
            <tr class="small fw-bold">
                <td>Total</td>
                <td class="text-end text-nowrap">{!! $cs['totals']['grossFormatted'] !!}</td>
                <td class="text-end text-nowrap">{!! $cs['totals']['taxFormatted'] !!}</td>
                <td class="text-end text-nowrap">{!! $cs['totals']['netFormatted'] !!}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Section B: Dutch tax form summary --}}
<div class="col-12 col-lg-4">
    <strong style="font-size: 0.95rem;">Dutch Tax Return - Fields to fill in</strong>
    <table class="table table-sm mb-0" style="margin-top: 0.75rem;">
        <thead>
            <tr class="small">
                <th>Field</th>
                <th class="text-end">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr class="small">
                <td>Gross dividend on shares or interest on bonds</td>
                <td class="text-end text-nowrap fw-bold">
                    {!! $cs['totals']['grossFormatted'] !!}
                </td>
            </tr>
            <tr class="small">
                <td>
                    Withheld Dutch dividend tax
                    <i class="fa fa-info-circle"
                        style="margin-left: 0.25rem; font-size: 0.8rem; color: #6c757d;"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-bs-title="Dutch bronbelasting withheld by NL-domiciled companies only. Withholding tax from other countries (e.g. US NRA tax) is reported separately under foreign tax."></i>
                </td>
                <td class="text-end text-nowrap fw-bold">
                    {!! $cs['dutchDividendTaxFormatted'] !!}
                </td>
            </tr>
            <tr class="small">
                <td>Has foreign tax been withheld?</td>
                <td class="text-end fw-bold">
                    {{ !empty($cs['foreignTaxByCountry']) ? 'Yes' : 'No' }}
                </td>
            </tr>
        </tbody>
    </table>
    @if(!empty($cs['foreignTaxByCountry']))
    <div style="margin-top: 0.75rem;">
        <small class="text-muted fw-bold">Foreign tax per country</small>
        <table class="table table-sm mb-0" style="margin-top: 0.25rem;">
            <tbody>
                @foreach($cs['foreignTaxByCountry'] as $foreign)
                <tr class="small">
                    <td class="fw-bold" style="width: 15%;">{{ $foreign['country'] }}</td>
                    <td class="text-nowrap">
                        Foreign tax: {!! $foreign['taxFormatted'] !!}
                    </td>
                    <td class="text-end text-nowrap">
                        Dividend: {!! $foreign['grossFormatted'] !!}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
