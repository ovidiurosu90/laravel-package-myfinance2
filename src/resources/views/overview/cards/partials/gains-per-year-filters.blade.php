{{-- The Gains Per Year filters (overview.scripts.gains-per-year): only gains or only losses (by a symbol's
     Total gain €), and a search on account names and symbols. While a filter is on, the year, account and footer
     totals add up the visible rows only.
     Sizing: plain buttons (no hidden radio inputs, which the page's mixed stylesheets gave a height that stretched
     the group); the search box takes the small buttons' exact box (font .7875rem, line height 1.5, padding
     .25rem .5rem, 1px border), overriding the theme's taller inputs (hence !important). The -0.2rem margins keep the
     row shorter than the card title, so the header stays as tall as the other cards' and all three centre on one
     line. --}}
<div class="ms-auto d-flex align-items-center gap-2" style="margin: -0.2rem 0;">
    <div class="btn-group btn-group-sm m-0" role="group" aria-label="Show gains or losses">
        <button type="button" class="btn btn-outline-secondary active" data-gpy-mode="all"
            aria-pressed="true">All</button>
        <button type="button" class="btn btn-outline-success" data-gpy-mode="gains"
            aria-pressed="false">Gains</button>
        <button type="button" class="btn btn-outline-danger" data-gpy-mode="losses"
            aria-pressed="false">Losses</button>
    </div>
    <input type="search" id="gpy-search" class="form-control form-control-sm"
        style="width: 12rem; margin: 0 !important; font-size: 0.7875rem !important; line-height: 1.5 !important;
            padding: 0.25rem 0.5rem !important; border-width: 1px !important; min-height: 0 !important;
            height: calc(1.5em + 0.5rem + 2px) !important; box-sizing: border-box !important;"
        placeholder="Account or symbol" aria-label="Search account names and symbols" autocomplete="off">
</div>
