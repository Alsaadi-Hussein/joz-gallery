<nav class="tabbar" aria-label="{{ __('shop.nav.menu') }}">
  <a href="{{ shop_route('home') }}" @class(['is-current' => request()->routeIs('home', 'en.home')])><x-ico name="home" /><span>{{ __('shop.nav.home') }}</span></a>
  <a href="{{ shop_route('home') }}#sections" @class(['is-current' => request()->routeIs('category', 'en.category')])><x-ico name="grid" /><span>{{ __('shop.nav.sections') }}</span></a>
  <a href="{{ shop_route('offers') }}" @class(['is-current' => request()->routeIs('offers', 'en.offers')])><x-ico name="tag" /><span>{{ __('shop.nav.offers') }}</span></a>
  <button type="button" data-open="cart"><x-ico name="bag" /><span>{{ __('shop.nav.cart') }}</span><i class="cart-count" data-cart-count hidden>0</i></button>
  <a href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer" class="tabbar__wa"><x-ico name="whatsapp" /><span>WhatsApp</span></a>
</nav>
