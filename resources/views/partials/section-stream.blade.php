{{--
  One section's products. While the visitor scrolls, app.js fetches the page named in
  data-stream-next and appends its block here, so the sections run on as one list.
  The heading shows only on a block that was appended for a new section.
--}}
<div class="sec-stream" data-stream>
  <section class="sec-block" data-stream-block
           data-key="{{ $nav->current }}"
           data-url="{{ canonical_url($products) }}"
           data-prev="{{ $nav->previous['url'] ?? '' }}"
           data-next-section="{{ $nav->next['url'] ?? '' }}"
           data-stream-next="{{ $streamNext ?? '' }}">
    <header class="listing__head listing__head--center sec-block__head" data-stream-head hidden>
      <h2 class="listing__title">{{ $title }}</h2>
      <p class="muted">{{ trans_choice('shop.product_count', $products->total()) }}</p>
    </header>
    <x-product-grid :products="$products" :empty="$empty" />
  </section>
</div>
