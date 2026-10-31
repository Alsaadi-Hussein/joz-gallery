@extends('layouts.app')

@section('title', $title.' | '.__('shop.meta.title_suffix'))
@section('description', $subtitle)
@section('canonical', canonical_url($products))

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[$title => null]" />

  <header class="listing__head listing__head--center">
    <h1 class="listing__title">{{ $title }}</h1>
    <p class="muted">{{ $subtitle }}</p>
    @if (($offerEndsAt ?? null) && $products->isNotEmpty())
      <x-countdown class="countdown--light" :ends-at="$offerEndsAt" />
    @endif
  </header>

  <x-product-grid :products="$products" :ranked="$ranked ?? false" />
</main>
@endsection
