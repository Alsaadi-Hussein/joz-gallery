@props(['order'])
@php
    use App\Support\Money;
@endphp
<ul class="summary__lines">
  @foreach ($order->items as $item)
    <li>
      <span>{{ $item->name_snapshot }}@if ($item->variant_snapshot) ({{ $item->variant_snapshot }})@endif × {{ $item->qty }}</span>
      <b>{{ Money::format($item->line_total) }}</b>
    </li>
  @endforeach
</ul>
<dl class="summary__totals">
  <div><dt>{{ __('shop.checkout.subtotal') }}</dt><dd>{{ Money::format($order->subtotal) }}</dd></div>
  @if ($order->discount)
    <div><dt>{{ __('shop.checkout.discount') }}</dt><dd>-{{ Money::format($order->discount) }}</dd></div>
  @endif
  <div><dt>{{ __('shop.checkout.delivery') }}</dt><dd>{{ Money::format($order->delivery_fee) }}</dd></div>
  <div class="summary__grand"><dt>{{ __('shop.checkout.total') }}</dt><dd>{{ Money::format($order->total) }}</dd></div>
</dl>
