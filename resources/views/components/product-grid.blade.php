@props(['products', 'ranked' => false, 'empty' => null])
@if ($products->isEmpty())
  <div class="empty-state">
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <p>{{ $empty ?? __('shop.listing.empty_collection') }}</p>
  </div>
@else
  <div class="pgrid">
    @foreach ($products as $product)
      <x-product-card :product="$product" :rank="$ranked ? $products->firstItem() + $loop->index : null" />
    @endforeach
  </div>
  {{ $products->onEachSide(1)->links('partials.pagination') }}
@endif
