@extends('layouts.app')

@php
    $name = $category->localized('name');
    $sortFields = collect(request()->only(['color', 'material', 'min', 'max']));
@endphp

@section('title', $name.' | '.__('shop.meta.title_suffix'))
@section('description', __('shop.meta.category', ['name' => $name]))
@section('canonical', canonical_url($products))

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="array_filter([
      $category->parent?->localized('name') ?? '' => $category->parent ? shop_route('category', $category->parent) : null,
      $name => null,
  ], fn ($url, $label) => $label !== '', ARRAY_FILTER_USE_BOTH)" />

  <header class="listing__head">
    <h1 class="listing__title">{{ $name }}</h1>
    <p class="muted">{{ trans_choice('shop.product_count', $products->total()) }}</p>
  </header>

  @if ($subcategories->isNotEmpty())
    <nav class="chips listing__subs" aria-label="{{ __('shop.nav.sections') }}">
      @foreach ($subcategories as $subcategory)
        <a class="chip" href="{{ shop_route('category', $subcategory) }}">{{ $subcategory->localized('name') }}</a>
      @endforeach
    </nav>
  @endif

  <div class="listing__layout">
    <details class="filters" data-filters @if ($filters->isActive()) open @endif>
      <summary class="filters__toggle"><x-ico name="tag" />{{ __('shop.listing.filters') }}</summary>
      <form class="filters__form" method="get" action="{{ url()->current() }}">
        @if ($filters->sort !== \App\Support\CatalogFilters::SORT_NEWEST)
          <input type="hidden" name="sort" value="{{ $filters->sort }}">
        @endif

        <fieldset class="filters__group">
          <legend>{{ __('shop.listing.price') }}</legend>
          <div class="filters__range">
            <label><span>{{ __('shop.listing.price_from') }}</span><input type="number" name="min" min="0" step="1000" inputmode="numeric" value="{{ $filters->min }}"></label>
            <label><span>{{ __('shop.listing.price_to') }}</span><input type="number" name="max" min="0" step="1000" inputmode="numeric" value="{{ $filters->max }}"></label>
          </div>
        </fieldset>

        @if ($facets['colors'] !== [])
          <fieldset class="filters__group">
            <legend>{{ __('shop.listing.color') }}</legend>
            @foreach ($facets['colors'] as $color)
              <label class="filters__check"><input type="checkbox" name="color[]" value="{{ $color }}" @checked(in_array($color, $filters->colors, true))><span>{{ $color }}</span></label>
            @endforeach
          </fieldset>
        @endif

        @if ($facets['materials'] !== [])
          <fieldset class="filters__group">
            <legend>{{ __('shop.listing.material') }}</legend>
            @foreach ($facets['materials'] as $material)
              <label class="filters__check"><input type="checkbox" name="material[]" value="{{ $material }}" @checked(in_array($material, $filters->materials, true))><span>{{ $material }}</span></label>
            @endforeach
          </fieldset>
        @endif

        <div class="filters__actions">
          <button class="btn btn--walnut btn--block" type="submit">{{ __('shop.listing.apply') }}</button>
          @if ($filters->isActive())
            <a class="link-arrow" href="{{ url()->current() }}">{{ __('shop.listing.clear') }}</a>
          @endif
        </div>
      </form>
    </details>

    <div class="listing__results">
      <form class="sorter" method="get" action="{{ url()->current() }}" data-autosubmit>
        @foreach ($sortFields as $field => $value)
          @foreach (\Illuminate\Support\Arr::wrap($value) as $item)
            <input type="hidden" name="{{ is_array($value) ? $field.'[]' : $field }}" value="{{ $item }}">
          @endforeach
        @endforeach
        <label for="sort">{{ __('shop.listing.sort') }}</label>
        <select id="sort" name="sort">
          @foreach (\App\Support\CatalogFilters::SORTS as $sort)
            <option value="{{ $sort }}" @selected($filters->sort === $sort)>{{ __('shop.listing.sort_'.$sort) }}</option>
          @endforeach
        </select>
        <noscript><button class="btn btn--ghost" type="submit">{{ __('shop.listing.apply') }}</button></noscript>
      </form>

      <x-product-grid :products="$products" :empty="__('shop.listing.empty')" />
    </div>
  </div>
</main>
@endsection
