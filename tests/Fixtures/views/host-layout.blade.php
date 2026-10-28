{{-- An app's own layout with section names of its own, for LayoutTest --}}
<!DOCTYPE html>
<html>
<head>
    <title>@yield('page_title')</title>
    @yield('head_extras')
</head>
<body>
    <div id="tst-host-layout">@yield('main')</div>
    @yield('end_scripts')
</body>
</html>
