@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $defaultDescription = __('shop.meta.description');
    $clientConfig = [
        'previewUrl' => shop_route('cart.preview'),
        'suggestUrl' => shop_route('search.suggest'),
        'whatsapp' => whatsapp_url(''),
        'i18n' => [
            'remove' => __('shop.cart.remove'),
            'added' => __('shop.cart.added'),
            'minus' => __('shop.cart.decrease'),
            'plus' => __('shop.cart.increase'),
            'maxStock' => __('shop.cart.max_stock'),
            'error' => __('shop.cart.error'),
            'waIntro' => __('shop.whatsapp.cart_intro'),
            'subtotal' => __('shop.cart.subtotal'),
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('shop.meta.title'))</title>
<meta name="description" content="@yield('description', $defaultDescription)">
@hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
<meta name="theme-color" content="#5b3e34">
<link rel="icon" type="image/png" href="{{ asset('assets/img/brand/favicon.png') }}">
<link rel="canonical" href="@yield('canonical', url()->current())">
<link rel="alternate" hreflang="ar" href="{{ alternate_url('ar', keepQuery: false) }}">
<link rel="alternate" hreflang="en" href="{{ alternate_url('en', keepQuery: false) }}">
<link rel="alternate" hreflang="x-default" href="{{ alternate_url('ar', keepQuery: false) }}">
<meta property="og:site_name" content="{{ __('shop.brand') }}">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_IQ' : 'en_US' }}">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="@yield('title', __('shop.meta.title'))">
<meta property="og:description" content="@yield('description', $defaultDescription)">
<meta property="og:image" content="@yield('og_image', asset('assets/img/og-default.jpg'))">
<meta property="og:url" content="@yield('canonical', url()->current())">
<meta name="twitter:card" content="summary_large_image">
@stack('meta')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Italiana&family=Tajawal:wght@300;400;500;700&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@include('partials.icons')
<div class="site" id="top" lang="{{ $locale }}" dir="{{ $direction }}">
  <a class="skip-link" href="#main">{{ __('shop.a11y.skip') }}</a>
  @include('partials.announce')
  @include('partials.header', ['otherLocale' => $otherLocale])

  @yield('content')

  @include('partials.footer')
  @include('partials.menu', ['otherLocale' => $otherLocale])
  @include('partials.search')
  @include('partials.cart')
  @include('partials.tabbar')

  <a class="wa-float" href="{{ whatsapp_url() }}" target="_blank" rel="noopener" aria-label="{{ __('shop.whatsapp.chat') }}"><x-ico name="whatsapp" /></a>

  <div class="toast" role="status" aria-live="polite" data-toast hidden></div>

  <script type="application/json" data-config>{!! json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
</div>
</body>
</html>
