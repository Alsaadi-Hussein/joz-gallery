@extends('layouts.app')

@php
    use App\Enums\OrderStatus;

    $steps = [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Shipping, OrderStatus::Delivered];
    $reached = isset($order) ? array_search($order->status, $steps, true) : false;
@endphp

@section('title', __('shop.track.title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[__('shop.track.title') => null]" />

  <div class="order-result">
    <header class="listing__head listing__head--center">
      <h1 class="listing__title">{{ __('shop.track.title') }}</h1>
      @unless (isset($order))<p class="muted">{{ __('shop.track.intro') }}</p>@endunless
    </header>

    @isset($order)
      <div class="order-panel">
        <p class="order-result__number"><span>{{ __('shop.thanks.number') }}</span><strong dir="ltr">{{ $order->number }}</strong></p>

        @if ($order->isCancelled())
          <p class="notice notice--error">{{ __('shop.track.cancelled') }}</p>
        @else
          <ol class="order-steps" aria-label="{{ __('shop.track.status') }}">
            @foreach ($steps as $index => $step)
              <li @class(['is-done' => $index <= $reached]) @if ($index === $reached) aria-current="step" @endif>
                <i></i><span>{{ __('shop.order_status.'.$step->value) }}</span>
              </li>
            @endforeach
          </ol>
        @endif

        <x-order-lines :order="$order" />
      </div>

      <div class="order-result__actions">
        <a class="btn btn--ghost btn--block" href="{{ shop_route('track') }}">{{ __('shop.track.another') }}</a>
      </div>
    @else
      <form class="order-panel" method="post" action="{{ shop_route('track.lookup') }}">
        @csrf
        <x-field name="number" :label="__('shop.track.number')" :hint="__('shop.track.number_hint')" required autocomplete="off" dir="ltr" />
        <x-field name="phone" :label="__('shop.checkout.phone')" :hint="__('shop.checkout.phone_hint')" required type="tel" inputmode="tel" autocomplete="tel" dir="ltr" />
        <button class="btn btn--walnut btn--block" type="submit">{{ __('shop.track.submit') }}</button>
      </form>
    @endisset
  </div>
</main>
@endsection
