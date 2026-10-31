@php
    // Same as the 404 page: nothing sets the language when a limit stops the request early.
    if (request()->is('en', 'en/*')) {
        app()->setLocale('en');
    }
@endphp
@extends('layouts.app')

@section('title', __('shop.errors.too_many_title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <div class="empty-state">
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <h1 class="listing__title">{{ __('shop.errors.too_many_title') }}</h1>
    <p>{{ __('shop.errors.too_many_text') }}</p>
    <a class="btn btn--walnut" href="{{ shop_route('home') }}">{{ __('shop.errors.back_home') }}<x-ico name="arrow" class="ico-dir" /></a>
  </div>
</main>
@endsection
