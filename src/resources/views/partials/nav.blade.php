@if (Auth::check())
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="navbar-dropdown-myfinance2" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        {!! trans('myfinance2::titles.navbarDropdownFinance') !!}
    </a>
    <div class="dropdown-menu" aria-labelledby="navbar-dropdown-myfinance2">
        <a class="dropdown-item {{ Request::is('ledger-transactions') ? 'active' : null }}" href="{{ url('/ledger-transactions') }}">
            {!! trans('myfinance2::ledger.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('funding') ? 'active' : null }}" href="{{ url('/funding') }}">
            {!! trans('myfinance2::funding.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('watchlist-symbols') ? 'active' : null }}" href="{{ url('/watchlist-symbols') }}">
            {!! trans('myfinance2::watchlistsymbols.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('trades') ? 'active' : null }}" href="{{ url('/trades') }}">
            {!! trans('myfinance2::trades.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('positions') ? 'active' : null }}" href="{{ url('/positions') }}">
            {!! trans('myfinance2::positions.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('cash-balances') ? 'active' : null }}" href="{{ url('/cash-balances') }}">
            {!! trans('myfinance2::cashbalances.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('dividends') ? 'active' : null }}" href="{{ url('/dividends') }}">
            {!! trans('myfinance2::dividends.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('timeline') ? 'active' : null }}" href="{{ url('/timeline') }}">
            {!! trans('myfinance2::timeline.titles.dashboard') !!}
        </a>
    </div>
</li>
@endif

