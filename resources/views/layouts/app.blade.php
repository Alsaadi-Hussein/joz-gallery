@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $defaultDescription = __('shop.meta.description');
    $clientConfig = [
        'previewUrl' => shop_route('cart.preview'),
        'suggestUrl' => shop_route('search.suggest'),
        'quoteUrl' => shop_route('checkout.quote'),
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
@include('partials.head')
</head>
<body class="@yield('body_class')">
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

  <a class="wa-float" href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('shop.whatsapp.chat') }}"><x-ico name="whatsapp" /></a>

  <div class="toast" role="status" aria-live="polite" data-toast hidden></div>

  <script type="application/json" data-config>{!! json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
</div>
</body>
</html>
