{{-- Moves the "12M vs tiers" counts (general/partials/tier-verdict-summary) into the list's DataTables toolbar, right
     after "entries per page", once the table is built; shown in place if there is no toolbar. --}}
<script type="module">
$(document).ready(function ()
{
    const $summary = $('#tier-verdict-summary');
    if (!$summary.length) {
        return;
    }

    function place()
    {
        const $length = $('.dt-container .dt-length').first();
        if (!$length.length || $summary.data('placed')) {
            return !!$summary.data('placed');
        }
        $length.parent().addClass('d-flex align-items-center flex-wrap').css('column-gap', '1.5rem');
        $summary.insertAfter($length).removeClass('d-none').addClass('d-flex').data('placed', true);

        return true;
    }

    $(document).on('init.dt', place);
    if (!place()) {
        // No toolbar yet: wait for it, and never leave the counts hidden.
        setTimeout(function ()
        {
            if (!place()) {
                $summary.removeClass('d-none').addClass('d-flex mx-3 mt-2');
            }
        }, 1500);
    }
});
</script>
