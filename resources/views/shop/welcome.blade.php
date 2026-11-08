@php
    $locale = app()->getLocale();
    $direction = $locale === 'ar' ? 'rtl' : 'ltr';
    $otherLocale = $locale === 'ar' ? 'en' : 'ar';
    $defaultDescription = __('shop.meta.description');
    $viteEntries = ['resources/css/app.css', 'resources/js/welcome.js'];
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
@include('partials.head')
</head>
<body class="page-welcome">
@include('partials.icons')
<div class="site" lang="{{ $locale }}" dir="{{ $direction }}">
  <main id="main" class="welcome">
    <div class="welcome__media" aria-hidden="true">
      <picture>
        <source media="(orientation: portrait)" srcset="{{ asset('assets/img/welcome/poster-portrait.webp') }}">
        <img class="welcome__poster" src="{{ asset('assets/img/welcome/poster-wide.webp') }}" alt="" width="1600" height="900" fetchpriority="high">
      </picture>
      <video class="welcome__video" muted loop playsinline preload="none" data-welcome-video
             data-portrait="{{ asset('assets/video/landing-portrait.mp4') }}"
             data-wide="{{ asset('assets/video/landing-wide.mp4') }}"></video>
    </div>

    <div class="welcome__top">
      <a class="lang-switch lang-switch--light" href="{{ alternate_url($otherLocale) }}" hreflang="{{ $otherLocale }}" aria-label="{{ __('shop.language.switch_label') }}">{{ __('shop.language.switch_full') }}</a>
    </div>

    <div class="welcome__center">
      <img class="welcome__logo" src="{{ asset('assets/img/brand/logo-cream.png') }}" alt="{{ __('shop.brand') }}" width="406" height="396">
      <h1 class="sr-only">{{ __('shop.brand') }}</h1>
      <p class="welcome__tagline"><span>{{ __('shop.hero.tagline_1') }}</span><span>{{ __('shop.hero.tagline_2') }}</span></p>
      <a class="btn btn--cream btn--lg welcome__enter" href="{{ shop_route('home') }}">{{ __('shop.welcome.enter') }}<x-ico name="arrow" class="ico-dir" /></a>
    </div>

    <div class="welcome__contact">
      <p class="welcome__label">{{ __('shop.welcome.contact') }}</p>
      <ul class="welcome__links">
        <li><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer"><x-ico name="whatsapp" /><span>WhatsApp</span></a></li>
        <li><a href="{{ $store['instagram_url'] }}" target="_blank" rel="noopener noreferrer"><x-ico name="instagram" /><span>Instagram</span></a></li>
        <li><a href="tel:+{{ \App\Models\Setting::get('whatsapp_number') }}"><x-ico name="phone" /><span>{{ __('shop.welcome.call') }}</span></a></li>
        <li><a href="{{ whatsapp_url(__('shop.whatsapp.projects')) }}" target="_blank" rel="noopener noreferrer"><x-ico name="grid" /><span>{{ __('shop.projects.short') }}</span></a></li>
      </ul>
      <p class="welcome__meta"><span dir="ltr">{{ $store['whatsapp'] }}</span> · <span dir="ltr">{{ '@'.$store['instagram'] }}</span></p>
      <p class="welcome__meta">{{ __('shop.welcome.delivery') }}</p>
    </div>
  </main>
</div>
</body>
</html>
