@use('ovidiuro\myfinance2\App\Models\PriceAlert')
@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting')
@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent')
@use('ovidiuro\myfinance2\App\Models\DipBuyingSetting')
@use('ovidiuro\myfinance2\App\Models\Order')
@role(['admin', 'financeadmin'])
@php
    $activeOrdersCount = Order::whereIn('status', ['DRAFT', 'PLACED'])->count();
    $activeAlertsCount = PriceAlert::where('status', 'ACTIVE')->count();
    $enabledPeakCount = PeakProximityAlertSetting::where('user_id', auth()->id())
        ->where('status', PeakProximityAlertSetting::ENABLED)
        ->count();
    $openPeakActionable = PeakProximityAlertEvent::where('user_id', auth()->id())
        ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
        ->where('classification', PeakProximityAlertEvent::CLASS_ACTIONABLE)
        ->count();
    $openPeakInfo = PeakProximityAlertEvent::where('user_id', auth()->id())
        ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
        ->where('classification', PeakProximityAlertEvent::CLASS_INFO)
        ->count();
    $dipEnabled = DipBuyingSetting::where('user_id', auth()->id())
        ->where('status', DipBuyingSetting::ENABLED)
        ->where('pool_amount_eur', '>', 0)
        ->exists();

    $alertsActive = Request::is('price-alerts') || Request::is('price-alerts/*')
        || Request::is('peak-proximity-alerts') || Request::is('peak-proximity-alerts/*')
        || Request::is('dip-buying-alerts') || Request::is('dip-buying-alerts/*');
    $tradingActive = Request::is('orders') || Request::is('orders/*')
        || Request::is('trades')
        || Request::is('stock-splits') || Request::is('stock-splits/*')
        || Request::is('cash-balances')
        || Request::is('dividends') || Request::is('timeline');
    $fundingActive = Request::is('ledger-transactions') || Request::is('funding');
    $setupActive = Request::is('currencies') || Request::is('accounts');
@endphp
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#"
       id="navbar-dropdown-myfinance2" role="button"
       data-bs-toggle="dropdown" data-bs-auto-close="outside"
       aria-haspopup="true" aria-expanded="false">
        {!! trans('myfinance2::titles.navbarDropdownFinance') !!}
        @if ($openPeakActionable > 0)
            <span class="badge bg-danger ms-1"
                  data-bs-toggle="tooltip" title="Peak-proximity alerts that suggest action">{{ $openPeakActionable }}</span>
        @endif
    </a>
    <div class="dropdown-menu" aria-labelledby="navbar-dropdown-myfinance2">
        <a class="dropdown-item {{ Request::is('finance-home') ? 'active' : null }}"
           href="{{ url('/finance-home') }}">
            {!! trans('myfinance2::titles.home') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('overview') ? 'active' : null }}" href="{{ url('/overview') }}">
            {!! trans('myfinance2::overview.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('watchlist-symbols') ? 'active' : null }}"
           href="{{ url('/watchlist-symbols') }}">
            {!! trans('myfinance2::watchlistsymbols.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('positions') ? 'active' : null }}" href="{{ url('/positions') }}">
            {!! trans('myfinance2::positions.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ Request::is('returns') ? 'active' : null }}" href="{{ url('/returns') }}">
            {!! trans('myfinance2::returns.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        {{-- Alerts --}}
        <div class="dropdown-item myfinance2-submenu {{ $alertsActive ? 'active show' : null }}"
             role="button" aria-haspopup="true" aria-expanded="{{ $alertsActive ? 'true' : 'false' }}">
            <span class="myfinance2-submenu-label">
                Alerts
                @if ($openPeakActionable > 0)
                    <span class="badge bg-danger ms-1"
                          data-bs-toggle="tooltip" title="Action suggested">{{ $openPeakActionable }}</span>
                @endif
                @if ($openPeakInfo > 0)
                    <span class="badge bg-secondary ms-1"
                          data-bs-toggle="tooltip" title="For your awareness">{{ $openPeakInfo }}</span>
                @endif
            </span>
            <i class="fa fa-chevron-right myfinance2-submenu-caret"></i>
            <div class="dropdown-menu myfinance2-submenu-menu">
                <a class="dropdown-item
                    {{ Request::is('price-alerts') || Request::is('price-alerts/*') ? 'active' : null }}"
                   href="{{ url('/price-alerts') }}">
                    {!! trans('myfinance2::alerts.titles.dashboard') !!}
                    @if ($activeAlertsCount > 0)
                        <span class="badge bg-success ms-1">{{ $activeAlertsCount }}</span>
                    @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ Request::is('peak-proximity-alerts') ? 'active' : null }}"
                   href="{{ url('/peak-proximity-alerts') }}">
                    Peak-Proximity Alerts
                    @if ($enabledPeakCount > 0)
                        <span class="badge bg-success ms-1">{{ $enabledPeakCount }}</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ Request::is('peak-proximity-alerts/inbox') ? 'active' : null }}"
                   href="{{ url('/peak-proximity-alerts/inbox') }}">
                    Inbox
                    @if ($openPeakActionable > 0)
                        <span class="badge bg-danger ms-1"
                              data-bs-toggle="tooltip" title="Action suggested">{{ $openPeakActionable }}</span>
                    @endif
                    @if ($openPeakInfo > 0)
                        <span class="badge bg-secondary ms-1"
                              data-bs-toggle="tooltip" title="For your awareness">{{ $openPeakInfo }}</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ Request::is('peak-proximity-alerts/history') ? 'active' : null }}"
                   href="{{ url('/peak-proximity-alerts/history') }}">
                    History
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ Request::is('dip-buying-alerts') ? 'active' : null }}"
                   href="{{ url('/dip-buying-alerts') }}">
                    Dip Buying Plan
                    @if ($dipEnabled)
                        <span class="badge bg-success ms-1">on</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ Request::is('dip-buying-alerts/backtest') ? 'active' : null }}"
                   href="{{ url('/dip-buying-alerts/backtest') }}">
                    Backtest
                </a>
                <a class="dropdown-item ps-4
                    {{ Request::is('dip-buying-alerts/history') ? 'active' : null }}"
                   href="{{ url('/dip-buying-alerts/history') }}">
                    History
                </a>
            </div>
        </div>
        <div class="dropdown-divider"></div>

        {{-- Trading --}}
        <div class="dropdown-item myfinance2-submenu {{ $tradingActive ? 'active show' : null }}"
             role="button" aria-haspopup="true" aria-expanded="{{ $tradingActive ? 'true' : 'false' }}">
            <span class="myfinance2-submenu-label">
                Trading
                @if ($activeOrdersCount > 0)
                    <span class="badge bg-success ms-1">{{ $activeOrdersCount }}</span>
                @endif
            </span>
            <i class="fa fa-chevron-right myfinance2-submenu-caret"></i>
            <div class="dropdown-menu myfinance2-submenu-menu">
                <a class="dropdown-item {{ Request::is('orders') || Request::is('orders/*') ? 'active' : null }}"
                   href="{{ url('/orders') }}">
                    {!! trans('myfinance2::orders.titles.dashboard') !!}
                    @if ($activeOrdersCount > 0)
                        <span class="badge bg-success ms-1">{{ $activeOrdersCount }}</span>
                    @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('trades') ? 'active' : null }}" href="{{ url('/trades') }}">
                    {!! trans('myfinance2::trades.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('dividends') ? 'active' : null }}"
                   href="{{ url('/dividends') }}">
                    {!! trans('myfinance2::dividends.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('cash-balances') ? 'active' : null }}"
                   href="{{ url('/cash-balances') }}">
                    {!! trans('myfinance2::cashbalances.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ Request::is('stock-splits') || Request::is('stock-splits/*') ? 'active' : null }}"
                   href="{{ url('/stock-splits') }}">
                    {!! trans('myfinance2::splits.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('timeline') ? 'active' : null }}"
                   href="{{ url('/timeline') }}">
                    {!! trans('myfinance2::timeline.titles.dashboard') !!}
                </a>
            </div>
        </div>
        <div class="dropdown-divider"></div>

        {{-- Funding --}}
        <div class="dropdown-item myfinance2-submenu {{ $fundingActive ? 'active show' : null }}"
             role="button" aria-haspopup="true" aria-expanded="{{ $fundingActive ? 'true' : 'false' }}">
            <span class="myfinance2-submenu-label">Funding</span>
            <i class="fa fa-chevron-right myfinance2-submenu-caret"></i>
            <div class="dropdown-menu myfinance2-submenu-menu">
                <a class="dropdown-item {{ Request::is('ledger-transactions') ? 'active' : null }}"
                   href="{{ url('/ledger-transactions') }}">
                    {!! trans('myfinance2::ledger.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('funding') ? 'active' : null }}"
                   href="{{ url('/funding') }}">
                    {!! trans('myfinance2::funding.titles.dashboard') !!}
                </a>
            </div>
        </div>
        <div class="dropdown-divider"></div>

        {{-- Setup --}}
        <div class="dropdown-item myfinance2-submenu {{ $setupActive ? 'active show' : null }}"
             role="button" aria-haspopup="true" aria-expanded="{{ $setupActive ? 'true' : 'false' }}">
            <span class="myfinance2-submenu-label">Setup</span>
            <i class="fa fa-chevron-right myfinance2-submenu-caret"></i>
            <div class="dropdown-menu myfinance2-submenu-menu">
                <a class="dropdown-item {{ Request::is('currencies') ? 'active' : null }}"
                   href="{{ url('/currencies') }}">
                    {!! trans('myfinance2::currencies.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ Request::is('accounts') ? 'active' : null }}"
                   href="{{ url('/accounts') }}">
                    {!! trans('myfinance2::accounts.titles.dashboard') !!}
                </a>
            </div>
        </div>
    </div>
</li>

<style>
    .myfinance2-submenu {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
    }
    .myfinance2-submenu .myfinance2-submenu-caret {
        font-size: .75rem;
        opacity: .55;
        margin-left: .75rem;
    }
    .myfinance2-submenu.show .myfinance2-submenu-caret {
        opacity: .9;
    }
    .myfinance2-submenu > .myfinance2-submenu-menu {
        position: absolute;
        top: -.5rem;
        left: 100%;
        margin: 0;
        display: none;
        min-width: 15rem;
    }
    .myfinance2-submenu.show > .myfinance2-submenu-menu {
        display: block;
    }
    .myfinance2-submenu.drop-left > .myfinance2-submenu-menu {
        left: auto;
        right: 100%;
    }
</style>

<script type="module">
    const root = document.getElementById('navbar-dropdown-myfinance2');
    if (root) {
        const menu = root.nextElementSibling;
        const submenus = menu.querySelectorAll('.myfinance2-submenu');
        // The submenu for the current page is rendered open; keep it as the default.
        const defaultOpen = menu.querySelector('.myfinance2-submenu.show');

        const flipIfOverflow = (submenu) => {
            const panel = submenu.querySelector('.myfinance2-submenu-menu');
            submenu.classList.toggle('drop-left', panel.getBoundingClientRect().right > window.innerWidth);
        };

        const openSubmenu = (submenu) => {
            submenus.forEach((other) => {
                if (other !== submenu) {
                    other.classList.remove('show', 'drop-left');
                    other.setAttribute('aria-expanded', 'false');
                }
            });
            submenu.classList.add('show');
            submenu.setAttribute('aria-expanded', 'true');
            flipIfOverflow(submenu);
        };

        // Reset to the default state (only the current page's group open) when the dropdown closes.
        const resetToDefault = () => {
            submenus.forEach((submenu) => {
                submenu.classList.remove('show', 'drop-left');
                submenu.setAttribute('aria-expanded', 'false');
            });
            if (defaultOpen) {
                defaultOpen.classList.add('show');
                defaultOpen.setAttribute('aria-expanded', 'true');
            }
        };

        submenus.forEach((submenu) => {
            submenu.addEventListener('click', (event) => {
                // Clicks on the actual links inside the submenu should navigate normally.
                if (event.target.closest('.myfinance2-submenu-menu')) {
                    return;
                }
                event.preventDefault();
                event.stopPropagation();

                if (submenu.classList.contains('show')) {
                    submenu.classList.remove('show', 'drop-left');
                    submenu.setAttribute('aria-expanded', 'false');
                } else {
                    openSubmenu(submenu);
                }
            });
        });

        // Re-flip the pre-opened submenu once the dropdown is actually visible (needs layout).
        root.addEventListener('shown.bs.dropdown', () => {
            if (defaultOpen && defaultOpen.classList.contains('show')) {
                flipIfOverflow(defaultOpen);
            }
        });
        // Restore the default open group whenever the Finance dropdown closes.
        root.addEventListener('hidden.bs.dropdown', resetToDefault);
    }
</script>
@endrole
