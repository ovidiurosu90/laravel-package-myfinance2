{{-- The body of the confirmation on save (tier-confirm-modal): the order and the serious warnings still to confirm.
     $warnings: those warnings; $action, $symbol, $quantity, $price. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if(!empty($warnings))
<p class="mb-2">
    <strong>{{ $action }}@if(!empty($quantity)) {{ MoneyFormat::get_formatted_number_plain($quantity + 0,
        floor($quantity) == $quantity ? 0 : 4) }}@endif {{ $symbol }}@if(!empty($price)) at
        {{ MoneyFormat::get_formatted_price_plain($price) }}@endif</strong>
    goes against {{ count($warnings) }} {{ count($warnings) === 1 ? 'serious warning' : 'serious warnings' }}:
</p>
<ul class="mb-2">
    @foreach($warnings as $warning)
        <li><strong>{{ $warning['label'] }}:</strong> {{ $warning['text'] }}</li>
    @endforeach
</ul>
<p class="text-muted small mb-0">The warnings are saved with the order, marked as confirmed, and show as a red cross
    in the order list.</p>
@endif
