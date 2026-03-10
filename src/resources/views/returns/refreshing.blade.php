@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <div class="card">
                <div class="card-body py-5">
                    <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h4 class="mb-2">Refreshing Returns Data</h4>
                    <p class="text-muted mb-0">
                        Recalculating returns for all years. This may take a minute&hellip;
                    </p>
                    <p class="text-muted mt-3 mb-0" id="refresh-elapsed" style="font-size: 0.85rem;"></p>
                    <p class="text-danger mt-3 mb-0 d-none" id="refresh-timeout-msg" style="font-size: 0.85rem;">
                        This is taking longer than expected.
                        You can <a href="{{ route('myfinance2::returns.index', ['year' => $selectedYear]) }}">
                            go to the returns page
                        </a> and it will load once the background refresh finishes.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

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
@endsection
