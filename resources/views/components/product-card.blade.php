@props(['product', 'rank' => null])
@php
    $quote = app(\App\Services\PriceService::class)->quote($product);
    $url = shop_route('product', $product);
    $name = $product->localized('name');
    $inStock = $product->effective_stock > 0;
@endphp
<article class="pcard">
  <div class="pcard__visual">
    <a class="pcard__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">
      <img src="{{ $product->imageUrl('thumb') }}" alt="" width="600" height="750" loading="lazy" decoding="async">
    </a>
    <div class="pcard__badges">
      @if ($rank)<span class="badge badge--rank">{{ $rank }}</span>@endif
      @if (! $inStock)
        <span class="badge badge--soldout">{{ __('shop.product.sold_out') }}</span>
      @else
        @if ($quote->hasDiscount())<span class="badge badge--sale">-{{ $quote->percentOff() }}%</span>@endif
        @if ($product->is_new)<span class="badge badge--new">{{ __('shop.product.new') }}</span>@endif
      @endif
    </div>
    @if ($inStock && $product->variants->isEmpty())
      <button class="pcard__add" type="button" data-add="{{ $product->id }}" data-name="{{ $name }}" aria-label="{{ __('shop.cart.add_named', ['name' => $name]) }}">
        <x-ico name="bag" /><span class="pcard__add-label">{{ __('shop.cart.add') }}</span>
      </button>
    @elseif ($inStock)
      <a class="pcard__add" href="{{ $url }}" aria-label="{{ __('shop.cart.choose_option') }}: {{ $name }}">
        <x-ico name="bag" /><span class="pcard__add-label">{{ __('shop.cart.choose_option') }}</span>
      </a>
    @endif
  </div>
  <div class="pcard__body">
    <span class="pcard__cat">{{ $product->category->localized('name') }}</span>
    <h3 class="pcard__title"><a href="{{ $url }}">{{ $name }}</a></h3>
    <x-price :quote="$quote" />
  </div>
</article>
