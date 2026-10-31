<dialog class="drawer drawer--end" data-dialog="cart" aria-label="{{ __('shop.nav.cart') }}">
  <div class="drawer__head">
    <h2 class="drawer__title">{{ __('shop.cart.title') }} <span class="muted" data-cart-count-text></span></h2>
    <button class="icon-btn" type="button" data-close aria-label="{{ __('shop.a11y.close') }}"><x-ico name="close" /></button>
  </div>
  <div class="cart-empty" data-cart-empty>
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <p>{{ __('shop.cart.empty') }}</p>
    <button class="btn btn--ghost" type="button" data-close>{{ __('shop.cart.start') }}</button>
  </div>
  <ul class="cart-lines" data-cart-lines></ul>
  <div class="drawer__foot cart-foot" data-cart-foot hidden>
    <div class="cart-total"><span>{{ __('shop.cart.subtotal') }}</span><strong data-cart-subtotal></strong></div>
    <p class="muted small">{{ __('shop.cart.delivery_note') }}</p>
    <a class="btn btn--walnut btn--block" href="{{ shop_route('checkout') }}">{{ __('shop.cart.checkout') }}<x-ico name="arrow" class="ico-dir" /></a>
    <a class="btn btn--sage btn--block" href="{{ whatsapp_url() }}" target="_blank" rel="noopener" data-cart-whatsapp><x-ico name="whatsapp" />{{ __('shop.whatsapp.order') }}</a>
    <a class="link-arrow cart-foot__view" href="{{ shop_route('cart') }}">{{ __('shop.cart.view') }}<x-ico name="arrow" class="ico-dir" /></a>
  </div>
</dialog>
