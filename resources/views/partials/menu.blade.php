<dialog class="drawer drawer--start" data-dialog="menu" aria-label="{{ __('shop.nav.menu') }}">
  <div class="drawer__head">
    <img src="{{ asset('assets/img/brand/logo-walnut.png') }}" alt="{{ __('shop.brand') }}" width="72" height="70" loading="lazy">
    <button class="icon-btn" type="button" data-close aria-label="{{ __('shop.a11y.close') }}"><x-ico name="close" /></button>
  </div>
  <nav class="drawer__nav">
    <a href="{{ shop_route('home') }}">{{ __('shop.nav.home') }}</a>
    <details open>
      <summary>{{ __('shop.nav.sections') }}</summary>
      @foreach ($menuSections as $section)
        <a class="sub" href="{{ shop_route('category', $section) }}">{{ $section->localized('name') }}</a>
      @endforeach
    </details>
    <a href="{{ shop_route('offers') }}" class="is-accent">{{ __('shop.nav.offers') }}</a>
    <a href="{{ shop_route('best-sellers') }}">{{ __('shop.nav.best_sellers') }}</a>
    <a href="{{ shop_route('new') }}">{{ __('shop.nav.new') }}</a>
    <a href="{{ shop_route('track') }}">{{ __('shop.nav.track') }}</a>
    <a href="{{ shop_route('page', 'about') }}">{{ __('shop.nav.about') }}</a>
    <a href="{{ shop_route('page', 'contact') }}">{{ __('shop.nav.contact') }}</a>
  </nav>
  <div class="drawer__foot">
    <a class="btn btn--sage btn--block" href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer"><x-ico name="whatsapp" />{{ __('shop.whatsapp.chat') }}</a>
    <a class="lang-switch lang-switch--wide" href="{{ alternate_url($otherLocale) }}" hreflang="{{ $otherLocale }}">{{ __('shop.language.switch_long') }}</a>
  </div>
</dialog>
