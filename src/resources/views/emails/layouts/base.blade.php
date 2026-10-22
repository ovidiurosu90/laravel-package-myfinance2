{{-- The shared frame of every MyFinance2 email: one stylesheet, the dark header (title and an optional sub-line),
     the body and the "MyFinance2 · <what this is>" footer, so the emails cannot drift apart. Sections a child sets:
     width (px, default 720; 1024 for the per-symbol alerts), styles (its own CSS only), title, subtitle (optional),
     body, brand (the footer's name, default MyFinance2; the main app's own emails pass theirs), footer-title and
     footer (optional second footer line).
     The title leads with one icon per meaning, the same in every email: 🔔 an alert to act on (price, peak-proximity,
     portfolio peak, dip buying), ⚠️ a problem to look at (split warning, returns missing quotes, daily log errors),
     ✅ a change confirmed (alert created); a stock split applied shows ✂️, paused / resumed alerts ⏸ / ▶ (as their
     button). Always the colour emoji form (with U+FE0F where the symbol has a plain text form too). --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="robots" content="noindex,nofollow" />
    <style>
        body { background-color: #F9F9F9; color: #222; font: 14px/1.6 Helvetica, Arial, sans-serif; margin: 0;
            padding: 20px; }
        .container { max-width: @yield('width', '720')px; margin: 0 auto; background: #fff; border: 1px solid #ddd;
            border-radius: 6px; overflow: hidden; }
        .header { background: #1a1a2e; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .header h1 a { color: #fff; text-decoration: underline; }
        .header .sub { margin-top: 6px; font-size: 13px; color: #c9c9d6; }
        .body { padding: 24px; }
        .section { margin-bottom: 20px; padding: 16px; background: #f8f9fa; border-radius: 4px;
            border-left: 4px solid #6c757d; }
        .section h2 { margin: 0 0 12px; font-size: 15px; font-weight: 600; }
        .section p { margin: 0 0 8px; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 4px; }
        .text-muted { color: #6c757d; }
        .text-nowrap, .text-success, .text-danger { white-space: nowrap; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-dark { color: #000 !important; }
        .text-white { color: #fff !important; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700;
            white-space: nowrap; color: #fff; }
        .bg-primary   { background: #0d6efd; color: #fff; }
        .bg-success   { background: #28a745; color: #fff; }
        .bg-danger    { background: #dc3545; color: #fff; }
        .bg-warning   { background: #ffc107; color: #000; }
        .bg-info      { background: #0dcaf0; color: #000; }
        .bg-secondary { background: #6c757d; color: #fff; }
        .bg-orange    { background: #e67e22; color: #fff; }
        .bg-light     { background: #f1f3f5; color: #000; border: 1px solid #dee2e6; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 6px 8px; border: 1px solid #dee2e6; font-size: 13px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; font-weight: 600; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        /* Notes get their own full-width line under a table row, joined to it visually. */
        tr.has-notes td { border-bottom: none; }
        td.notes { border-top: none; padding-top: 0; color: #495057; }
        td.notes .label { display: inline; margin: 0 4px 0 0; }
        a.sym-link { color: #0d6efd; text-decoration: none; }
        .actions { margin-top: 20px; }
        .action-link { display: inline-block; margin: 6px 8px 6px 0; padding: 8px 16px; border-radius: 4px;
            background: #0d6efd; color: #fff !important; font-size: 13px; font-weight: 600; text-decoration: none; }
        .action-link.secondary { background: #6c757d; }
        .action-link.warning { background: #ffc107; color: #000 !important; }
        .footer { padding: 16px 24px; background: #f8f9fa; border-top: 1px solid #ddd; font-size: 12px;
            color: #6c757d; }
        @yield('styles')
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>@yield('title')</h1>
        @hasSection('subtitle')
        <div class="sub">@yield('subtitle')</div>
        @endif
    </div>
    <div class="body">
        @yield('body')
    </div>
    <div class="footer">
        <strong>@yield('brand', 'MyFinance2')</strong> &middot; @yield('footer-title')
        @hasSection('footer')
        <br>@yield('footer')
        @endif
    </div>
</div>
</body>
</html>
