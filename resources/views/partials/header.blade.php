<header class="site-header" data-header>
  <div class="site-header__inner wrap">
    <button class="icon-btn site-header__menu" type="button" data-open="menu" aria-label="{{ __('shop.nav.menu') }}"><x-ico name="menu" /></button>

    <a class="brand" href="{{ shop_route('home') }}" aria-label="{{ __('shop.brand') }}">
      <img class="brand__emblem" src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="44" height="44">
      <img class="brand__word" src="{{ asset('assets/img/brand/wordmark-walnut.png') }}" alt="Joz Gallery for home" width="132" height="30">
    </a>

    <nav class="main-nav" aria-label="{{ __('shop.nav.menu') }}">
      <ul>
        <li class="mega">
          <a href="{{ shop_route('home') }}#sections">{{ __('shop.nav.sections') }}</a>
          <div class="mega__panel">
            <ul class="mega__grid wrap">
              @foreach ($menuSections as $section)
                <li>
                  <a class="mega__item" href="{{ shop_route('category', $section) }}">
                    <img src="{{ $section->getFirstMediaUrl('image', 'thumb') }}" alt="" width="200" height="150" loading="lazy" decoding="async">
                    <span>{{ $section->localized('name') }}</span>
                  </a>
                  @if ($section->children->isNotEmpty())
                    <ul class="mega__subs">
                      @foreach ($section->children as $child)
                        <li><a href="{{ shop_route('category', $child) }}">{{ $child->localized('name') }}</a></li>
                      @endforeach
                    </ul>
                  @endif
                </li>
              @endforeach
            </ul>
          </div>
        </li>
        <li><a href="{{ shop_route('offers') }}" class="is-accent">{{ __('shop.nav.offers') }}</a></li>
        <li><a href="{{ shop_route('best-sellers') }}">{{ __('shop.nav.best_sellers') }}</a></li>
        <li><a href="{{ shop_route('new') }}">{{ __('shop.nav.new') }}</a></li>
        <li><a href="{{ shop_route('page', 'about') }}">{{ __('shop.nav.about') }}</a></li>
        <li><a href="{{ shop_route('page', 'contact') }}">{{ __('shop.nav.contact') }}</a></li>
      </ul>
    </nav>

    <div class="site-header__tools">
      <a class="lang-switch" href="{{ alternate_url($otherLocale) }}" hreflang="{{ $otherLocale }}" aria-label="{{ __('shop.language.switch_label') }}">{{ __('shop.language.switch_short') }}</a>
      <button class="icon-btn" type="button" data-open="search" aria-label="{{ __('shop.nav.search') }}"><x-ico name="search" /></button>
      <button class="icon-btn cart-btn" type="button" data-open="cart" aria-label="{{ __('shop.nav.cart') }}">
        <x-ico name="bag" /><span class="cart-count" data-cart-count hidden>0</span>
      </button>
    </div>
  </div>
</header>
