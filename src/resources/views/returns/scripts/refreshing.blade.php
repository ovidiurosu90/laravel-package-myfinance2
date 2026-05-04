<script type="module">
    const redirectUrl = {{ Js::from(route('myfinance2::returns.index', ['year' => $selectedYear])) }};
    const statusUrl = {{ Js::from(route('myfinance2::returns.refresh-status')) }};
    const startedAt = Date.now();
    const timeoutMs = 5 * 60 * 1000;
    const pollIntervalMs = 3000;

    function updateElapsed()
    {
        const seconds = Math.floor((Date.now() - startedAt) / 1000);
        document.getElementById('refresh-elapsed').textContent = `Elapsed: ${seconds}s`;
    }

    async function poll()
    {
        updateElapsed();

        if (Date.now() - startedAt > timeoutMs) {
            document.getElementById('refresh-timeout-msg').classList.remove('d-none');
            return;
        }

        try {
            const response = await fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            if (data.status === 'complete') {
                window.location.href = redirectUrl;
                return;
            }
        } catch (e) {
            // network blip — keep polling
        }

        setTimeout(poll, pollIntervalMs);
    }

    setTimeout(poll, pollIntervalMs);
</script>
