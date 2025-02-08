<script type="module">
$(document).ready(function()
{
    // Confirm Form Submit Modal
    // $('{{ $formTrigger }}').on('show.bs.modal', function (e)
    document.getElementById('{{ $formTrigger }}').addEventListener('shown.bs.modal', (e) =>
    {
        var message = $(e.relatedTarget).attr('data-message');
        var title = $(e.relatedTarget).attr('data-title');
        var form = $(e.relatedTarget).closest('form');
        $(this).find('.modal-body p').text(message);
        $(this).find('.modal-title').text(title);
        $(this).find('.modal-footer #confirm').data('form', form);
    });
    $('#{{ $formTrigger }}').find('.modal-footer #confirm').on('click', function()
    {
        $(this).data('form')[0].submit();
    });
});
</script>

