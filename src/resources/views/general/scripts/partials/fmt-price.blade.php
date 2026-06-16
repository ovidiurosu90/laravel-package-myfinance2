// Mirror MoneyFormat::get_formatted_price (per-share precision): 0 decimals
// with a thousands separator at >= 1,000, 2 decimals >= 1, 4/6 for penny and
// micro-cap prices. Keeps every chart axis, label and 52W range bar consistent
// with the server-rendered header price. Included inside <script type="module">
// blocks, so each consumer gets its own scoped copy from this single source.
function fmtPrice(v)
{
    const n = Number(v);
    if (!Number.isFinite(n)) {
        return '';
    }
    const abs = Math.abs(n);
    let decimals;
    if (abs >= 1000) {
        decimals = 0;
    } else if (abs >= 1) {
        decimals = 2;
    } else if (abs >= 0.01) {
        decimals = 4;
    } else {
        decimals = 6;
    }
    const out = n.toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
    return (out === '0.000000' || out === '-0.000000') ? '0' : out;
}
