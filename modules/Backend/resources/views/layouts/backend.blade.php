<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="turbolinks-cache-control" content="no-cache">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? '' }}</title>
    <link rel="icon" href="{{ asset('jw-styles/juzaweb/images/favicon.ico') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback"/>
    <script async src="https://www.gstatic.com/charts/loader.js"></script>

    @include('cms::components.juzaweb_langs')

    @do_action('juzaweb_header')

    @yield('header')

</head>

<body class="juzaweb__menuLeft--dark juzaweb__topbar--fixed juzaweb__menuLeft--unfixed juzaweb__menuLeft--shadow">
<div class="juzaweb__layout juzaweb__layout--hasSider">

    <div class="juzaweb__menuLeft">
        <div class="juzaweb__menuLeft__mobileTrigger"><span></span></div>

        <div class="juzaweb__menuLeft__outer">
            <div class="juzaweb__menuLeft__logo__container">
                <a href="/{{ config('juzaweb.admin_prefix') }}">
                <div class="juzaweb__menuLeft__logo">
                    <img src="{{ asset('jw-styles/juzaweb/images/logo.svg') }}" class="mr-2" alt="Juzaweb">
                    <div class="juzaweb__menuLeft__logo__name">JuzaWeb</div>
                    <div class="juzaweb__menuLeft__logo__descr">Cms</div>
                </div>

                </a>
                {{--<div class="juzaweb__menuLeft__logo">
                    <div class="juzaweb__menuLeft__logo__name">
                        <a href="/{{ config('juzaweb.admin_prefix') }}">
                            <img src="{{ asset('jw-styles/juzaweb/images/logo.png') }}" alt="">
                        </a>
                    </div>
                </div>--}}
            </div>

            <div class="juzaweb__menuLeft__scroll jw__customScroll">
                @include('cms::backend.menu_left')
            </div>
        </div>
    </div>
    <div class="juzaweb__menuLeft__backdrop"></div>

    <div class="juzaweb__layout">
        <div class="juzaweb__layout__header">
            @include('cms::backend.menu_top')
        </div>

        <div class="juzaweb__layout__content">
            @if(!request()->is(config('juzaweb.admin_prefix')))
                {{
                    jw_breadcrumb(
                        'admin',
                         [
                            [
                                'title' => $title
                            ]
                        ]
                    )
                }}
            @else
                <div class="mb-3"></div>
            @endif

            @if ($version = cache()->store('file')->get(cache_prefix('check_cms_update')))
            @if ($version != 1)
            <div class="alert alert-warning w-50 ml-3">
                <a href="https://juzaweb.com/documentation/changelog" target="_blank">JuzaWeb CMS {{ $version }}</a> {{ __('is available!') }} <a href="{{ route('admin.update') }}">{{ __('Please update now') }}</a>.
            </div>
            @endif

            @endif

            <h4 class="font-weight-bold ml-3 text-capitalize">{{ $title }}</h4>

            <div class="juzaweb__utils__content">

                @do_action('backend_message')

                @php
                    $messages = get_backend_message();
                @endphp

                @foreach($messages as $message)
                    <div class="alert alert-{{ $message['status'] == 'error' ? 'danger' : $message['status'] }} jw-message">
                        <button type="button" class="close close-message" data-dismiss="alert" aria-label="Close" data-id="{{ $message['id'] }}">
                            <span aria-hidden="true">×</span>
                        </button>
                        {!! e_html($message['message']) !!}
                    </div>
                @endforeach

                @if(session()->has('message'))
                    <div class="alert alert-{{ session()->get('status') == 'error' ? 'danger' : 'success' }} jw-message">{{ session()->get('message') }}</div>
                @endif

                <div id="jquery-message"></div>

                @yield('content')
            </div>
        </div>

        <div class="juzaweb__layout__footer">
            <div class="juzaweb__footer">
                <div class="juzaweb__footer__inner">
                    <a href="https://juzaweb.com" target="_blank" rel="noopener noreferrer" class="juzaweb__footer__logo">
                        Juzaweb - Build website professional
                        <span></span>
                    </a>
                    <br />
                    <p class="mb-0">
                        Copyright © {{ date('Y') }} {{ get_config('title') }} - Provided by Juzaweb
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="form-images-template">
    @component('cms::components.image-item', [
        'name' => '{name}',
        'path' => '{path}',
        'url' => '{url}',
    ])

    @endcomponent
</template>

<div id="show-modal"></div>

<form action="{{ route('logout') }}" method="post" style="display: none" class="form-logout">
    @csrf
</form>

<script type="text/javascript">
    $.extend( $.validator.messages, {
        required: "{{ trans('cms::app.this_field_is_required') }}",
    });

    $(".form-ajax").validate();

    $(".auth-logout").on('click', function () {
        $('.form-logout').submit();
    });
</script>

@do_action('juzaweb_footer')

@yield('footer')
</body>
</html>
