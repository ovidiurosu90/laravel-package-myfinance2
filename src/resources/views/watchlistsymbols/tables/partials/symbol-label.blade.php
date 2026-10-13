{{-- A symbol in a narrow table cell: an unlisted symbol's prefix ("UNLISTED_") smaller and muted, the rest
     ("ACME") at normal size, to save width. A listed symbol prints as is. $symbol is the symbol. --}}
@php $labelParts = \ovidiuro\myfinance2\App\Services\UnlistedSymbol::labelParts($symbol); @endphp
@if($labelParts['prefix'] !== '')<span class="text-muted fw-normal" style="font-size:0.7em;">{{
    $labelParts['prefix'] }}</span>@endif{{ $labelParts['name'] }}
