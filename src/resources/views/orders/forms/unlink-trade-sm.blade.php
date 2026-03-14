<form action="{{ route('myfinance2::orders.unlink-trade', $id) }}" method="POST"
    accept-charset="utf-8">
    {{ csrf_field() }}
    <button class="btn w-100 btn-outline-secondary btn-sm" type="submit"
        title="{{ trans('myfinance2::orders.tooltips.unlink-trade') }}">
        {!! trans('myfinance2::orders.buttons.unlink-trade') !!}
    </button>
</form>
