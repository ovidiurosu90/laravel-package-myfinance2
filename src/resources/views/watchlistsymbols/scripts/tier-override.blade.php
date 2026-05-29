<script type="module">
$(document).ready(function ()
{
    const saveFormAction   = '{{ route('myfinance2::symbol-tier-overrides.store') }}';
    const deleteBaseUrl    = '{{ rtrim(route('myfinance2::symbol-tier-overrides.destroy', ['symbol' => 'PLACEHOLDER']), '') }}'.replace('/PLACEHOLDER', '');

    const $saveForm        = $('#tier-override-save-form');
    const $removeForm      = $('#tier-override-remove-form');
    const $removeBtn       = $('#tier-override-remove');
    const $symbolLabel     = $('#tier-override-symbol');
    const $symbolInput     = $('#tier-override-symbol-input');
    const $select          = $('#tier-override-select');
    const $note            = $('#tier-override-note');
    const $computed        = $('#tier-override-computed');

    document.getElementById('tier-override-modal').addEventListener('shown.bs.modal', (e) =>
    {
        const btn      = e.relatedTarget;
        const symbol   = $(btn).data('symbol');
        const tier     = $(btn).data('current-tier');
        const computed = $(btn).data('computed-tier');
        const hasOverride = $(btn).data('has-override') === true;
        const note     = $(btn).data('override-note') || '';

        $symbolLabel.text(symbol);
        $symbolInput.val(symbol);
        $saveForm.attr('action', saveFormAction);
        $removeForm.attr('action', deleteBaseUrl + '/' + encodeURIComponent(symbol));

        $select.val(tier || computed || 'GOLD');
        $note.val(note);
        $computed.text(computed || 'none');

        $removeForm.toggle(hasOverride);
        $removeBtn.toggle(hasOverride);
    });
});
</script>
