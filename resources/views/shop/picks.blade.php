@extends('layouts.app')

@section('title', __('shop.nav.picks').' | '.__('shop.meta.title_suffix'))
@section('description', __('shop.picks.subtitle'))
@section('canonical', canonical_url($products))

@section('content')
<main id="main" class="listing listing--sections" data-section-page
      data-prev="{{ $nav->previous['url'] ?? '' }}" data-next="{{ $nav->next['url'] ?? '' }}">
  @include('partials.section-tabs')

  <div class="wrap listing__body" data-swipe-area>
    <x-breadcrumb :items="[__('shop.nav.sections') => shop_route('home').'#sections', __('shop.nav.picks') => null]" />

    <header class="listing__head listing__head--center">
      <img class="listing__seal" src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="56" height="56">
      <h1 class="listing__title">{{ __('shop.nav.picks') }}</h1>
      <p class="muted">{{ __('shop.picks.subtitle') }}</p>
      @include('partials.swipe-hint')
    </header>

    @php($streamNext = $products->hasMorePages() ? $products->nextPageUrl() : ($nav->next['url'] ?? null))
    @include('partials.section-stream', ['title' => __('shop.nav.picks'), 'empty' => null])

    @include('partials.section-pager')
  </div>
</main>
@endsection
