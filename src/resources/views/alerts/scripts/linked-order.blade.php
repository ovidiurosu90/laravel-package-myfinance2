{{-- Warns, on the edit form of an alert an open order keeps in sync, as soon as an edit would break the link, and
     asks for a confirmation on save (alerts.partials.linked-order-warning). Mirrors PriceAlert::matchesOrder(); the
     server checks again on save and sends the form back with the confirmation open when it was skipped. Reset
     restores the stored (linked) values of the fields the link depends on, keeping the other edits (notes, ...). --}}
<script type="module">
$(document).ready(function ()
{
    const $warning = $('#linked-order-warning');
    const $form    = $warning.closest('form');
    const $modal   = $('#linked-order-confirm-modal');
    const expected = $warning.data('expected');
    const linked   = $warning.data('linked');
    const labels   = $warning.data('field-labels');
    const template = String($warning.data('template'));
    const detail   = String($warning.data('detail'));
    // Built server-side (escaped): the link to the order, opening in a new tab.
    const orderLink = String($warning.data('order-link'));
    // The expiry is in the app timezone: compare it with the server's clock (moved on since the page loaded), not
    // the browser's. Both are read the same way, so the browser's timezone cancels out.
    const parseLocal  = (text) => new Date(String(text).replace(' ', 'T')).getTime();
    const serverStart = parseLocal($warning.data('now'));
    const pageStart   = Date.now();
    const serverNow   = () => serverStart + (Date.now() - pageStart);
    let confirmed  = false;
    // The broken fields the warning was dismissed for: it stays hidden until they change.
    let dismissedFor = null;

    function value(name)
    {
        const $inputs = $form.find('[name="' + name + '"]');
        const $input  = $inputs.is(':radio') ? $inputs.filter(':checked') : $inputs;

        return String($input.val() ?? '').trim();
    }

    // The fields whose current value no longer matches the order (see PriceAlert::matchesOrder()).
    function brokenFields()
    {
        const fields = [];
        if (value('symbol').toUpperCase() !== expected.symbol) {
            fields.push('symbol');
        }
        if (value('alert_type') !== expected.alert_type) {
            fields.push('alert_type');
        }
        if ((value('status') || 'ACTIVE') !== 'ACTIVE') {
            fields.push('status');
        }
        if (value('target_mode') !== 'FIXED') {
            fields.push('target_mode');
        } else {
            const price = parseFloat(value('target_price'));
            if (isNaN(price) || Math.abs(price - expected.target_price) > 0.000001) {
                fields.push('target_price');
            }
        }
        const expiry = value('expires_at');
        if (!expiry || !(parseLocal(expiry) > serverNow())) {
            fields.push('expires_at');
        }

        return fields;
    }

    // "the symbol", "the symbol and the status", "the symbol, the status and the expiry".
    function listText(fields)
    {
        const names = fields.map((field) => labels[field]);
        if (names.length < 2) {
            return names[0] || 'these fields';
        }

        return names.slice(0, -1).join(', ') + ' and ' + names[names.length - 1];
    }

    function escapeHtml(text)
    {
        return $('<div>').text(text).html();
    }

    function update()
    {
        const fields = brokenFields();
        const key    = fields.join(',');
        if (!fields.length) {
            dismissedFor = null;
        }
        $('.linked-order-warning-text').html(escapeHtml(template)
            .replace(':fields', escapeHtml(listText(fields)))
            .replace(':order', orderLink) + '<br>' + escapeHtml(detail));
        const shown = fields.length > 0 && key !== dismissedFor;
        $warning.toggleClass('d-none', !shown).toggleClass('d-flex', shown);

        return fields;
    }

    // Sets a field and fires the events its own scripts listen to (native, so the expiry picker hears
    // it too); a selectize select through its API.
    function setField(name, fieldValue)
    {
        const $inputs = $form.find('[name="' + name + '"]');
        if ($inputs.is(':radio')) {
            $inputs.filter('[value="' + fieldValue + '"]').prop('checked', true).trigger('change');
            return;
        }
        const selectize = $inputs[0] && $inputs[0].selectize;
        if (selectize) {
            selectize.setValue(fieldValue);
            return;
        }
        $inputs.val(fieldValue);
        $inputs.each(function ()
        {
            this.dispatchEvent(new Event('input', { bubbles: true }));
            this.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    $('#linked-order-reset').on('click', function ()
    {
        $(this).tooltip('hide');
        Object.keys(linked).forEach((name) => setField(name, linked[name]));
        update();
    });
    $('#linked-order-dismiss').on('click', function ()
    {
        dismissedFor = brokenFields().join(',');
        update();
    });

    function openConfirmation()
    {
        update();
        // Bootstrap's data API (the modal is not a jQuery plugin in this build).
        document.getElementById('linked-order-confirm-open').click();
    }

    $form.on('input change', 'input, select, textarea', update);
    // The expiry picker reports its changes through its own event (not namespaced jQuery "change").
    document.getElementById('expires-at-picker')?.addEventListener('change.td', update);

    $form.on('submit', function (event)
    {
        if (confirmed || !update().length) {
            return;
        }
        event.preventDefault();
        openConfirmation();
    });
    $('#linked-order-confirm-accept').on('click', function ()
    {
        $('<input type="hidden" name="confirm_unlink" value="1">').appendTo($form);
        confirmed = true;
        $(this).prop('disabled', true);
        $form.trigger('submit');
    });
    $modal.on('shown.bs.modal', function ()
    {
        $('#linked-order-confirm-back').trigger('focus');
    });

    update();
    if ($modal.data('open-on-load') === true || $modal.data('open-on-load') === 'true') {
        openConfirmation();
    }
});
</script>
