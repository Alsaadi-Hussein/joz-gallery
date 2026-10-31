<footer class="site-footer">
  <div class="site-footer__pattern" aria-hidden="true"></div>
  <div class="wrap site-footer__grid">
    <div class="site-footer__brand">
      <img src="{{ asset('assets/img/brand/logo-cream.png') }}" alt="{{ __('shop.brand') }}" width="150" height="146" loading="lazy">
      <p class="site-footer__slogan">{{ __('shop.slogan') }}</p>
      <p>{{ __('shop.footer.about') }}</p>
    </div>

    <nav class="site-footer__col" aria-label="{{ __('shop.footer.shop') }}">
      <h2>{{ __('shop.footer.shop') }}</h2>
      <ul>
        @foreach ($menuSections as $section)
          <li><a href="{{ shop_route('category', $section) }}">{{ $section->localized('name') }}</a></li>
        @endforeach
        <li><a href="{{ shop_route('offers') }}">{{ __('shop.nav.offers') }}</a></li>
      </ul>
    </nav>

    <nav class="site-footer__col" aria-label="{{ __('shop.footer.help') }}">
      <h2>{{ __('shop.footer.help') }}</h2>
      <ul>
        <li><a href="{{ shop_route('track') }}">{{ __('shop.nav.track') }}</a></li>
        <li><a href="{{ shop_route('page', 'delivery') }}">{{ __('shop.footer.delivery') }}</a></li>
        <li><a href="{{ shop_route('page', 'returns') }}">{{ __('shop.footer.returns') }}</a></li>
        <li><a href="{{ shop_route('page', 'about') }}">{{ __('shop.nav.about') }}</a></li>
        <li><a href="{{ shop_route('page', 'privacy') }}">{{ __('shop.footer.privacy') }}</a></li>
      </ul>
    </nav>

    <div class="site-footer__col">
      <h2>{{ __('shop.footer.contact') }}</h2>
      <ul class="contact-list">
        <li>
          <x-ico name="phone" />
          <span><small>{{ __('shop.footer.phone_whatsapp') }}</small><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener" dir="ltr">{{ $store['whatsapp'] }}</a></span>
        </li>
        <li>
          <x-ico name="instagram" />
          <span><small>Instagram</small><a href="{{ $store['instagram_url'] }}" target="_blank" rel="noopener" dir="ltr">{{ '@'.$store['instagram'] }}</a></span>
        </li>
        @if ($store['address'])
          <li><x-ico name="pin" /><span>{{ $store['address'] }}</span></li>
        @endif
        <li><x-ico name="truck" /><span>{{ __('shop.footer.delivery_all') }}</span></li>
        <li><x-ico name="cash" /><span>{{ __('shop.footer.cash') }}</span></li>
      </ul>
    </div>
  </div>
  <div class="wrap site-footer__bottom">
    <p>{{ __('shop.footer.rights', ['year' => now()->year]) }}</p>
    <div class="site-footer__social">
      <a class="icon-btn" href="{{ $store['instagram_url'] }}" target="_blank" rel="noopener" aria-label="Instagram"><x-ico name="instagram" /></a>
      <a class="icon-btn" href="{{ whatsapp_url() }}" target="_blank" rel="noopener" aria-label="WhatsApp"><x-ico name="whatsapp" /></a>
    </div>
  </div>
</footer>
