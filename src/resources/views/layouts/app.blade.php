{{-- Every MyFinance2 page extends this and fills the package's own sections (myfinance2_title, myfinance2_styles,
     myfinance2_content, myfinance2_scripts). They render inside the app's layout (myfinance2.layout), in the app's
     sections named by myfinance2.layout_sections; without an app layout, inside the package's minimal page
     (myfinance2::layouts.standalone). README, "Layout". --}}
@php
    $myFinance2Sections = config('myfinance2.layout_sections');
@endphp
@extends(config('myfinance2.layout') ?: 'myfinance2::layouts.standalone')

@hasSection('myfinance2_title')
    @section($myFinance2Sections['title'])@yield('myfinance2_title')@endsection
@endif

@section($myFinance2Sections['styles'])
    @include('myfinance2::partials.assets')
    @yield('myfinance2_styles')
@endsection

@section($myFinance2Sections['content'])
    @if (config('myfinance2.flash_messages'))
        @include('myfinance2::partials.flash-messages')
    @endif
    @yield('myfinance2_content')
@endsection

@section($myFinance2Sections['scripts'])
    @yield('myfinance2_scripts')
@endsection
