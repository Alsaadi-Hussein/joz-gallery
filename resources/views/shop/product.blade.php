@extends('layouts.app')

@php
    $name = $product->localized('name');
    $description = $product->localized('description');
    $gallery = $product->getMedia('gallery');
    $inStock = $product->effective_stock > 0;
    $selected = $variants->firstWhere(fn ($variant) => $variant['stock'] > 0);
    $hasDimensions = $product->width_cm && $product->depth_cm && $product->height_cm;
    $metaDescription = $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $description), 160);
    // A product with options has no single price, so the message names the piece only.
    $orderMessage = $variants->isEmpty()
        ? __('shop.whatsapp.order_product', ['name' => $name, 'price' => \App\Support\Money::format($quote->final)])
        : __('shop.whatsapp.ask_product', ['name' => $name]);
    $crumbs = [];

    if ($product->category->parent) {
        $crumbs[$product->category->parent->localized('name')] = shop_route('category', $product->category->parent);
    }

    $crumbs[$product->category->localized('name')] = shop_route('category', $product->category);
    $crumbs[$name] = null;
@endphp

@section('title', ($product->meta_title ?: $name).' | '.__('shop.meta.title_suffix'))
@section('description', $metaDescription ?: __('shop.meta.description'))
@section('canonical', shop_route('product', $product))
@section('og_type', 'product')
@section('og_image', $product->imageUrl('share'))

@push('meta')
<meta property="product:price:amount" content="{{ $quote->final }}">
<meta property="product:price:currency" content="IQD">
@endpush

@section('content')
<main id="main" class="wrap product-page">
  <x-breadcrumb :items="$crumbs" />

  <div class="pdp">
    <div class="pdp__gallery" data-gallery>
      <div class="pdp__stage">
        @if ($gallery->isNotEmpty())
          <img src="{{ $gallery->first()->getUrl('large') }}"
               srcset="{{ $gallery->first()->getUrl('thumb') }} 600w, {{ $gallery->first()->getUrl('large') }} 1600w"
               sizes="(min-width: 1024px) 50vw, 100vw"
               alt="{{ $name }}" width="1600" height="2000" fetchpriority="high" decoding="async" data-gallery-stage>
        @else
          <img src="{{ $product->imageUrl() }}" alt="{{ $name }}" width="600" height="750">
        @endif
      </div>
      @if ($gallery->count() > 1)
        <ul class="pdp__thumbs">
          @foreach ($gallery as $media)
            <li>
              <button type="button" @class(['is-current' => $loop->first]) aria-label="{{ $name }} {{ $loop->iteration }}"
                      data-gallery-thumb data-src="{{ $media->getUrl('large') }}"
                      data-srcset="{{ $media->getUrl('thumb') }} 600w, {{ $media->getUrl('large') }} 1600w">
                <img src="{{ $media->getUrl('thumb') }}" alt="" width="120" height="150" loading="lazy" decoding="async">
              </button>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    <div class="pdp__info" data-product>
      <a class="pcard__cat pdp__section" href="{{ shop_route('category', $product->category) }}">{{ $product->category->localized('name') }}</a>
      <h1 class="pdp__title">{{ $name }}</h1>

      <div class="pdp__price">
        <x-price :quote="$quote" />
        @if ($quote->hasDiscount())<span class="badge badge--sale">-{{ $quote->percentOff() }}%</span>@endif
      </div>

      @if ($product->localized('material') || $product->localized('color') || $hasDimensions)
        <section class="pdp__section-block pdp__specs">
          <h2>{{ __('shop.product.details') }}</h2>
          <dl class="specs">
            @if ($product->localized('material'))
              <div><dt>{{ __('shop.product.material') }}</dt><dd>{{ $product->localized('material') }}</dd></div>
            @endif
            @if ($product->localized('color'))
              <div><dt>{{ __('shop.product.color') }}</dt><dd>{{ $product->localized('color') }}</dd></div>
            @endif
            @if ($hasDimensions)
              <div>
                <dt>{{ __('shop.product.dimensions') }}</dt>
                <dd>
                  {{ __('shop.product.dimensions_value', ['width' => $product->width_cm, 'depth' => $product->depth_cm, 'height' => $product->height_cm]) }}
                  <small class="muted">{{ __('shop.product.dimensions_note') }}</small>
                </dd>
              </div>
            @endif
          </dl>
        </section>
      @endif

      @if ($variants->isNotEmpty())
        <fieldset class="pdp__options">
          <legend>{{ __('shop.product.options') }}</legend>
          <div class="chips">
            @foreach ($variants as $variant)
              <label class="option">
                <input type="radio" name="variant" value="{{ $variant['id'] }}" data-variant-option
                       data-now="{{ $variant['now'] }}" data-old="{{ $variant['old'] }}" data-stock="{{ $variant['stock'] }}"
                       @checked($selected && $selected['id'] === $variant['id']) @disabled($variant['stock'] < 1)>
                <span>{{ $variant['label'] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>
      @endif

      <div class="pdp__actions">
        <button class="btn btn--walnut btn--block" type="button" data-add="{{ $product->id }}" data-variant="{{ $selected['id'] ?? '' }}" data-name="{{ $name }}" @disabled(! $inStock)>
          <x-ico name="bag" /><span>{{ $inStock ? __('shop.cart.add') : __('shop.product.sold_out') }}</span>
        </button>
        <a class="btn btn--wa btn--block" href="{{ whatsapp_url($orderMessage."\n".shop_route('product', $product)) }}" target="_blank" rel="noopener noreferrer">
          <x-ico name="whatsapp" />{{ __('shop.whatsapp.order') }}
        </a>
      </div>

      <p class="pdp__note"><x-ico name="truck" />{{ __('shop.product.delivery_note') }}</p>

      @if ($description)
        <section class="pdp__section-block">
          <h2>{{ __('shop.product.description') }}</h2>
          <p>{{ $description }}</p>
        </section>
      @endif

    </div>
  </div>

  @if ($related->isNotEmpty())
    <section class="block" aria-labelledby="related-title">
      <x-section-title id="related-title" :title="__('shop.product.related')" />
      <div class="pgrid">
        @foreach ($related as $item)
          <x-product-card :product="$item" />
        @endforeach
      </div>
    </section>
  @endif
</main>
@endsection
