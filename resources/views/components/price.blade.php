@props(['quote'])
<p {{ $attributes->merge(['class' => 'price']) }}>
  <span class="price__now" data-price-now>{{ \App\Support\Money::format($quote->final) }}</span>
  <del class="price__old" data-price-old @if (! $quote->hasDiscount()) hidden @endif>{{ \App\Support\Money::format($quote->base) }}</del>
</p>
