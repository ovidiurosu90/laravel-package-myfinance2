<script type="module">
$(document).ready(function()
{
    $('.market_status_countdown_to_open, .market_status_countdown_to_close').each(function()
    {
        var countdownDate = new Date($(this).data('timestamp') * 1000).getTime();
        var $element = $(this);

        // Update the count down every 1 second
        var x = setInterval(function() {

            // Get today's date and time
            var now = new Date().getTime();

            // Find the distance between now and the count down date
            var distance = countdownDate - now;

            // Time calculations for days, hours, minutes and seconds
            var days = Math.floor(distance / (1000 * 60 * 60 * 24));
            var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);

            // Display the result in the element with id="demo"
            var content = (days ? days + "d " : '') +
                (hours ? hours + "h " : '') +
                (minutes ? minutes + "m " : '') +
                (!days && !hours && !minutes && seconds ? seconds + "s " : '')
            $element.html(content);

            // If the count down is finished, write some text
            if (distance < 0) {
                clearInterval(x);
                $element.html('- DONE');
            }
        }, 1000);

        $(this).removeClass('d-none');
    });
});
</script>

