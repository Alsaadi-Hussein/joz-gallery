@extends('layouts.app')

@section('title', __('shop.cart.title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[__('shop.cart.title') => null]" />

  <header class="listing__head">
    <h1 class="listing__title">{{ __('shop.cart.title') }} <span class="muted" data-cart-count-text></span></h1>
  </header>

  <div class="empty-state" data-cart-empty>
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <p>{{ __('shop.cart.empty') }}</p>
    <a class="btn btn--ghost" href="{{ shop_route('home') }}#sections">{{ __('shop.cart.start') }}</a>
  </div>

  <div class="checkout">
    <ul class="cart-lines order-panel" data-cart-lines hidden></ul>

    <aside class="order-panel summary" data-cart-foot hidden>
      <div class="cart-total"><span>{{ __('shop.cart.subtotal') }}</span><strong data-cart-subtotal></strong></div>
      <p class="muted small">{{ __('shop.cart.delivery_note') }}</p>
      <a class="btn btn--walnut btn--block" href="{{ shop_route('checkout') }}">{{ __('shop.cart.checkout') }}<x-ico name="arrow" class="ico-dir" /></a>
      <a class="btn btn--sage btn--block" href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer" data-cart-whatsapp><x-ico name="whatsapp" />{{ __('shop.whatsapp.order') }}</a>
    </aside>
  </div>
</main>
@endsection
