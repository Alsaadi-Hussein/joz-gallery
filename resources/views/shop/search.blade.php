@extends('layouts.app')

@php
    $heading = $term !== '' ? __('shop.search.results_for', ['term' => $term]) : __('shop.nav.search');
@endphp

@section('title', $heading.' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[__('shop.nav.search') => null]" />

  <header class="listing__head listing__head--center">
    <h1 class="listing__title">{{ $heading }}</h1>
    @if ($term !== '')
      <p class="muted">{{ trans_choice('shop.product_count', $products->total()) }}</p>
    @endif
  </header>

  <form class="search-page" role="search" action="{{ shop_route('search') }}" method="get">
    <label class="sr-only" for="search-page-q">{{ __('shop.nav.search') }}</label>
    <input id="search-page-q" name="q" type="search" value="{{ $term }}" placeholder="{{ __('shop.search.placeholder') }}">
    <button class="btn btn--walnut" type="submit"><x-ico name="search" /><span class="sr-only">{{ __('shop.nav.search') }}</span></button>
  </form>

  <x-product-grid :products="$products" :empty="$term !== '' ? __('shop.search.empty') : __('shop.search.prompt')" />
</main>
@endsection
