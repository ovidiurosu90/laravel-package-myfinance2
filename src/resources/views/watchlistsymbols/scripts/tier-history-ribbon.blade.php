{{-- One Bootstrap tooltip per tier ribbon, created on first hover and updated as the mouse moves from one
     segment to the next (a tooltip per segment would mean hundreds of instances on the page). --}}
<script type="module">
    $(document).ready(function ()
    {
        // Bootstrap is loaded as a jQuery plugin here (no window.bootstrap); the plugin carries the class.
        const Tooltip = $.fn.tooltip.Constructor;

        function _content(segment, strip)
        {
            const $tip = $('<div>');
            $('<div>').text(segment.dataset.title || '').appendTo($tip);
            $('<div class="text-white-50">').text(strip.dataset.note || '').appendTo($tip);

            return $tip.html();
        }

        function _tooltip(strip)
        {
            return Tooltip.getOrCreateInstance(strip, {
                trigger: 'manual',
                html: true,
                customClass: 'tooltip-wide',
                title: ' ',
            });
        }

        $(document)
            .on('mouseover', '.tier-ribbon > span', function ()
            {
                const strip = this.parentElement;
                const tip   = _tooltip(strip);
                tip.setContent({ '.tooltip-inner': _content(this, strip) });
                tip.show();
            })
            .on('mouseleave click', '.tier-ribbon', function ()
            {
                const tip = Tooltip.getInstance(this);
                if (tip) {
                    tip.hide();
                }
            });
    });
</script>
