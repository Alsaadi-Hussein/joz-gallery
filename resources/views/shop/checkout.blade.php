@extends('layouts.app')

@php
    $cartError = $errors->first('cart') ?: ($errors->hasAny(['items', 'items.*']) ? __('shop.checkout.cart_invalid') : null);
@endphp

@section('title', __('shop.checkout.title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[__('shop.cart.title') => shop_route('cart'), __('shop.checkout.title') => null]" />

  <header class="listing__head">
    <h1 class="listing__title">{{ __('shop.checkout.title') }}</h1>
  </header>

  <div class="empty-state" data-checkout-empty hidden>
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <p>{{ __('shop.cart.empty') }}</p>
    <a class="btn btn--ghost" href="{{ shop_route('home') }}#sections">{{ __('shop.cart.start') }}</a>
  </div>

  <form class="checkout" method="post" action="{{ shop_route('checkout.store') }}" data-checkout>
    @csrf
    <input type="hidden" name="cart" data-checkout-cart>

    {{-- Out of sight for people, tempting for bots. --}}
    <div class="sr-only" aria-hidden="true">
      <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    </div>

    <div class="order-panel checkout__form">
      @if ($cartError)
        <p class="notice notice--error" role="alert">{{ $cartError }}</p>
      @endif

      <h2 class="order-panel__title">{{ __('shop.checkout.details') }}</h2>

      <x-field name="name" :label="__('shop.checkout.name')" required autocomplete="name" />
      <x-field name="phone" :label="__('shop.checkout.phone')" :hint="__('shop.checkout.phone_hint')" required type="tel" inputmode="tel" autocomplete="tel" dir="ltr" />

      <div class="field">
        <label for="governorate_id">{{ __('shop.checkout.governorate') }}</label>
        <select id="governorate_id" name="governorate_id" required data-checkout-governorate>
          <option value="">{{ __('shop.checkout.governorate_choose') }}</option>
          @foreach ($governorates as $governorate)
            <option value="{{ $governorate->id }}" @selected(old('governorate_id') == $governorate->id)>{{ $governorate->localized('name') }}</option>
          @endforeach
        </select>
        @error('governorate_id')<p class="field__error">{{ $message }}</p>@enderror
      </div>

      <x-field name="area" :label="__('shop.checkout.area')" required />
      <x-field name="landmark" :label="__('shop.checkout.landmark')" required />

      <div class="field">
        <label for="notes">{{ __('shop.checkout.notes') }}</label>
        <textarea id="notes" name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
        @error('notes')<p class="field__error">{{ $message }}</p>@enderror
      </div>

      <h2 class="order-panel__title">{{ __('shop.checkout.payment') }}</h2>
      <p class="pdp__note"><x-ico name="cash" />{{ __('shop.checkout.payment_cash') }}</p>
    </div>

    <aside class="order-panel summary">
      <h2 class="order-panel__title">{{ __('shop.checkout.summary') }}</h2>
      <ul class="summary__lines" data-summary-lines></ul>

      <div class="field">
        <label for="coupon">{{ __('shop.checkout.coupon') }}</label>
        <div class="field__inline">
          <input id="coupon" name="coupon" type="text" maxlength="50" autocomplete="off" dir="ltr" value="{{ old('coupon') }}" data-checkout-coupon>
          <button class="btn btn--ghost" type="button" data-checkout-apply>{{ __('shop.checkout.apply') }}</button>
        </div>
        <p class="field__hint" data-coupon-message hidden></p>
        @error('coupon')<p class="field__error">{{ $message }}</p>@enderror
      </div>

      <dl class="summary__totals">
        <div><dt>{{ __('shop.checkout.subtotal') }}</dt><dd data-summary-subtotal></dd></div>
        <div data-summary-discount-row hidden><dt>{{ __('shop.checkout.discount') }}</dt><dd data-summary-discount></dd></div>
        <div><dt>{{ __('shop.checkout.delivery') }}</dt><dd data-summary-delivery data-empty="{{ __('shop.checkout.delivery_choose') }}">{{ __('shop.checkout.delivery_choose') }}</dd></div>
        <div class="summary__grand"><dt>{{ __('shop.checkout.total') }}</dt><dd data-summary-total></dd></div>
      </dl>

      <button class="btn btn--walnut btn--block" type="submit" data-checkout-submit data-busy="{{ __('shop.checkout.submitting') }}">{{ __('shop.checkout.submit') }}</button>
    </aside>
  </form>
</main>
@endsection
