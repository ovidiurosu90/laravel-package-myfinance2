@if (session('message'))
    <div class="alert alert-{{ Session::get('status') }}
                status-box alert-dismissable fade show" role="alert">
        <a href="#" class="close" data-bs-dismiss="alert" aria-label="close">
            &times;
            <span class="sr-only">
                {!! trans('myfinance2::general.flash-messages.close') !!}
            </span>
        </a>
        {!! session('message') !!}
    </div>
@endif

@if (session('success'))
    <div class="alert alert-success alert-dismissable fade show" role="alert">
        <a href="#" class="close" data-bs-dismiss="alert" aria-label="close">
            &times;
            <span class="sr-only">
                {!! trans('myfinance2::general.flash-messages.close') !!}
            </span>
        </a>
        <h4>
            <i class="icon fa fas fa-check fa-fw" aria-hidden="true"></i>
            {!! trans('myfinance2::general.flash-messages.success') !!}
        </h4>
        {!! session('success') !!}
    </div>
@endif

@if(session()->has('status') && session()->get('status') == 'wrong')
    <div class="alert alert-danger status-box alert-dismissable fade show"
        role="alert">
        <a href="#" class="close" data-bs-dismiss="alert" aria-label="close">
            &times;
            <span class="sr-only">
                {!! trans('myfinance2::general.flash-messages.close') !!}
            </span>
        </a>
        {!! session('message') !!}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissable fade show" role="alert">
        <a href="#" class="close" data-bs-dismiss="alert" aria-label="close">
            &times;
            <span class="sr-only">
                {!! trans('myfinance2::general.flash-messages.close') !!}
            </span>
        </a>
        <h4>
            <i class="icon fa fas fa-warning fa-fw" aria-hidden="true"></i>
            {!! trans('myfinance2::general.flash-messages.error') !!}
        </h4>
        {!! session('error') !!}
    </div>
@endif

@if (session('errors') && count($errors) > 0)
    <div class="alert alert-danger alert-dismissable fade show" role="alert">
        <a href="#" class="close" data-bs-dismiss="alert" aria-label="close">
            &times;
            <span class="sr-only">
                {!! trans('myfinance2::general.flash-messages.close') !!}
            </span>
        </a>
        <h4>
            <i class="icon fa fas fa-warning fa-fw" aria-hidden="true"></i>
            <strong>
                {!! trans('myfinance2::general.flash-messages.whoops') !!}
            </strong>
            {!! trans('myfinance2::general.flash-messages.some-problems') !!}
        </h4>
        <ul>
            @foreach ($errors->all() as $error)
                <li>
                    {!! $error !!}
                </li>
            @endforeach
        </ul>
    </div>
@endif

