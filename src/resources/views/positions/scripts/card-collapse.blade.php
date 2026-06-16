<script type="module">
    $(document).ready(function () {
        // Remember each /positions card's collapsed/expanded state across page refreshes.
        // Restore by toggling classes directly (no bootstrap JS dependency), mirroring the working
        // /watchlist-symbols logic. Live summary toggling on user clicks stays in each card's own
        // small script; here we set the summary only while restoring (no show/hide event fires then).
        const cards = [
            { id: 'user-overview',    summary: '#user-overview-summary', toggle: '#user-overview-title',    defaultOpen: true  },
            { id: 'biggest-movers',   summary: '#movers-summary',        toggle: '#biggest-movers-title',   defaultOpen: false },
            { id: 'dip-buying-panel', summary: '#dip-buying-summary',    toggle: '#dip-buying-panel-title', defaultOpen: false },
            @foreach (($accountData ?? []) as $accountId => $value)
            { id: 'account-overview-{{ $accountId }}', summary: null, toggle: '#account-overview-title-{{ $accountId }}', defaultOpen: true },
            @endforeach
        ];

        cards.forEach(function (card) {
            const $body = $('#' + card.id);
            if (!$body.length) {
                return;
            }
            const $toggle  = $(card.toggle);
            const $summary = card.summary ? $(card.summary) : $();
            const lsKey    = 'positions-collapse:' + card.id;

            // Persist state when the user toggles the card (Bootstrap fires these on click).
            $body
                .on('show.bs.collapse', function () {
                    localStorage.setItem(lsKey, 'open');
                    $toggle.attr('title', 'Collapse');
                })
                .on('hide.bs.collapse', function () {
                    localStorage.setItem(lsKey, 'closed');
                    $toggle.attr('title', 'Expand');
                });

            // Restore: act only when the saved state differs from the markup default.
            const saved    = localStorage.getItem(lsKey);
            const wantOpen  = saved === null ? card.defaultOpen : (saved === 'open');
            if (wantOpen === $body.hasClass('show')) {
                return;
            }
            if (wantOpen) {
                $body.addClass('show');
                $toggle.removeClass('collapsed').attr('aria-expanded', 'true').attr('title', 'Collapse');
                $summary.addClass('d-none');
                $body.trigger('shown.bs.collapse'); // let any chart inside resize to the visible width
            } else {
                $body.removeClass('show');
                $toggle.addClass('collapsed').attr('aria-expanded', 'false').attr('title', 'Expand');
                $summary.removeClass('d-none');
            }
        });
    });
</script>
