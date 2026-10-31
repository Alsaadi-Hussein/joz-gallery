@extends('layouts.app')

@section('title', __('shop.thanks.title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  @if (session('order_placed'))
    {{-- Only right after ordering, so reopening this link later never empties a newer cart. --}}
    <script type="application/json" data-order-placed>{!! json_encode($order->only(['name', 'phone', 'governorate_id', 'area', 'landmark']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
  @endif

  <div class="order-result">
    <header class="listing__head listing__head--center">
      <span class="order-result__mark"><x-ico name="sparkle" /></span>
      <h1 class="listing__title">{{ __('shop.thanks.title') }}</h1>
      <p class="muted">{{ __('shop.thanks.text') }}</p>
    </header>

    <div class="order-panel">
      <p class="order-result__number"><span>{{ __('shop.thanks.number') }}</span><strong dir="ltr">{{ $order->number }}</strong></p>
      <x-order-lines :order="$order" />
    </div>

    <div class="order-result__actions">
      <a class="btn btn--sage btn--block" href="{{ whatsapp_url(\App\Support\OrderSummary::whatsapp($order)) }}" target="_blank" rel="noopener"><x-ico name="whatsapp" />{{ __('shop.thanks.whatsapp') }}</a>
      <a class="btn btn--ghost btn--block" href="{{ shop_route('track') }}">{{ __('shop.thanks.track') }}</a>
    </div>
  </div>
</main>
@endsection
