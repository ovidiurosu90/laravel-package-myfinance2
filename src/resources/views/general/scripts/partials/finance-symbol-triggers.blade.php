    $('#get-finance-data').on('click', function()
    {
        var symbol = $symbolInput.val();
        if (symbol) { applySmartPrefill(symbol); }
    });

    $symbolInput.on('blur', function()
    {
        @if (!empty($fetchGuard))if (!({!! $fetchGuard !!})) return;
        @endif
        var symbol = $symbolInput.val();
        if (symbol) { applySmartPrefill(symbol); }
    });

    @if (!empty($fetchGuard))
    if ($symbolInput.val() && ({!! $fetchGuard !!})) { applySmartPrefill($symbolInput.val()); }
    @else
    if ($symbolInput.val()) { applySmartPrefill($symbolInput.val()); }
    @endif
