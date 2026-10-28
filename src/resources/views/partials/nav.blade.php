@use('ovidiuro\myfinance2\App\Models\PriceAlert')
@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertSetting')
@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent')
@use('ovidiuro\myfinance2\App\Models\DipBuyingSetting')
@use('ovidiuro\myfinance2\App\Models\PortfolioPeakSetting')
@use('ovidiuro\myfinance2\App\Models\Order')
@use('ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService')
@can('myfinance2.access')
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
    $portfolioPeakEnabled = PortfolioPeakSetting::where('user_id', auth()->id())
        ->where('status', PortfolioPeakSetting::ENABLED)
        ->exists();
    // Windows currently inside their threshold (the settings table's "Fires? = yes" rows). Only
    // computed for users who opted in; the service caches it, so the nav stays cheap.
    $portfolioPeakFires = $portfolioPeakEnabled
        ? (new PortfolioPeakAlertService())->getTriggeredCount((int) auth()->id())
        : 0;

    // The open page, by route name (myfinance2::<name>), so the menu follows myfinance2.route_prefix
    $isPage = fn (string ...$names) => request()->routeIs(
        ...array_map(fn (string $name) => "myfinance2::{$name}", $names)
    );

    $alertsActive = $isPage('price-alerts.*', 'peak-proximity-alerts.*', 'dip-buying-alerts.*',
        'portfolio-peak-alerts.*');
    $tradingActive = $isPage('orders.*', 'trades.index', 'stock-splits.*', 'cash-balances.index', 'dividends.index',
        'timeline');
    $fundingActive = $isPage('ledger-transactions.index', 'funding');
    $setupActive = $isPage('currencies.index', 'accounts.index');
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
        <a class="dropdown-item {{ $isPage('home') ? 'active' : null }}"
           href="{{ route('myfinance2::home') }}">
            {!! trans('myfinance2::titles.home') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ $isPage('overview') ? 'active' : null }}" href="{{ route('myfinance2::overview') }}">
            {!! trans('myfinance2::overview.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ $isPage('watchlist-symbols.index') ? 'active' : null }}"
           href="{{ route('myfinance2::watchlist-symbols.index') }}">
            {!! trans('myfinance2::watchlistsymbols.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ $isPage('positions') ? 'active' : null }}"
           href="{{ route('myfinance2::positions') }}">
            {!! trans('myfinance2::positions.titles.dashboard') !!}
        </a>
        <div class="dropdown-divider"></div>

        <a class="dropdown-item {{ $isPage('returns.index') ? 'active' : null }}"
           href="{{ route('myfinance2::returns.index') }}">
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
                @if ($portfolioPeakFires > 0)
                    <span class="badge bg-warning text-dark ms-1" data-bs-toggle="tooltip"
                          title="Portfolio peak windows inside their threshold">{{ $portfolioPeakFires }}</span>
                @endif
            </span>
            <i class="fa fa-chevron-right myfinance2-submenu-caret"></i>
            <div class="dropdown-menu myfinance2-submenu-menu">
                <a class="dropdown-item
                    {{ $isPage('price-alerts.*') ? 'active' : null }}"
                   href="{{ route('myfinance2::price-alerts.index') }}">
                    {!! trans('myfinance2::alerts.titles.dashboard') !!}
                    @if ($activeAlertsCount > 0)
                        <span class="badge bg-success ms-1">{{ $activeAlertsCount }}</span>
                    @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ $isPage('peak-proximity-alerts.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::peak-proximity-alerts.index') }}">
                    Peak-Proximity Alerts
                    @if ($enabledPeakCount > 0)
                        <span class="badge bg-success ms-1">{{ $enabledPeakCount }}</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ $isPage('peak-proximity-alerts.inbox') ? 'active' : null }}"
                   href="{{ route('myfinance2::peak-proximity-alerts.inbox') }}">
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
                    {{ $isPage('peak-proximity-alerts.history') ? 'active' : null }}"
                   href="{{ route('myfinance2::peak-proximity-alerts.history') }}">
                    History
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ $isPage('dip-buying-alerts.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::dip-buying-alerts.index') }}">
                    Dip Buying Plan
                    @if ($dipEnabled)
                        <span class="badge bg-success ms-1">on</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ $isPage('dip-buying-alerts.backtest') ? 'active' : null }}"
                   href="{{ route('myfinance2::dip-buying-alerts.backtest') }}">
                    Backtest
                </a>
                <a class="dropdown-item ps-4
                    {{ $isPage('dip-buying-alerts.history') ? 'active' : null }}"
                   href="{{ route('myfinance2::dip-buying-alerts.history') }}">
                    History
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ $isPage('portfolio-peak-alerts.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::portfolio-peak-alerts.index') }}">
                    Portfolio Peak Alerts
                    @if ($portfolioPeakEnabled)
                        <span class="badge bg-success ms-1">on</span>
                    @endif
                    @if ($portfolioPeakFires > 0)
                        <span class="badge bg-warning text-dark ms-1" data-bs-toggle="tooltip"
                              title="Windows inside their threshold now (Fires? = yes)">{{ $portfolioPeakFires }}</span>
                    @endif
                </a>
                <a class="dropdown-item ps-4
                    {{ $isPage('portfolio-peak-alerts.history') ? 'active' : null }}"
                   href="{{ route('myfinance2::portfolio-peak-alerts.history') }}">
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
                <a class="dropdown-item {{ $isPage('orders.*') ? 'active' : null }}"
                   href="{{ route('myfinance2::orders.index') }}">
                    {!! trans('myfinance2::orders.titles.dashboard') !!}
                    @if ($activeOrdersCount > 0)
                        <span class="badge bg-success ms-1">{{ $activeOrdersCount }}</span>
                    @endif
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('trades.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::trades.index') }}">
                    {!! trans('myfinance2::trades.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('dividends.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::dividends.index') }}">
                    {!! trans('myfinance2::dividends.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('cash-balances.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::cash-balances.index') }}">
                    {!! trans('myfinance2::cashbalances.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item
                    {{ $isPage('stock-splits.*') ? 'active' : null }}"
                   href="{{ route('myfinance2::stock-splits.index') }}">
                    {!! trans('myfinance2::splits.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('timeline') ? 'active' : null }}"
                   href="{{ route('myfinance2::timeline') }}">
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
                <a class="dropdown-item {{ $isPage('ledger-transactions.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::ledger-transactions.index') }}">
                    {!! trans('myfinance2::ledger.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('funding') ? 'active' : null }}"
                   href="{{ route('myfinance2::funding') }}">
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
                <a class="dropdown-item {{ $isPage('currencies.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::currencies.index') }}">
                    {!! trans('myfinance2::currencies.titles.dashboard') !!}
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item {{ $isPage('accounts.index') ? 'active' : null }}"
                   href="{{ route('myfinance2::accounts.index') }}">
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
@endcan
