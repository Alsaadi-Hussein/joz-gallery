@extends('layouts.app')

@section('title', __('shop.soon.title').' | '.__('shop.meta.title_suffix'))
@section('robots', 'noindex')

@section('content')
<main id="main" class="listing wrap">
  <div class="empty-state">
    <img src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="88" height="88" loading="lazy">
    <h1 class="listing__title">{{ __('shop.soon.title') }}</h1>
    <p>{{ __('shop.soon.text') }}</p>
    <a class="btn btn--sage" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><x-ico name="whatsapp" />{{ __('shop.whatsapp.chat') }}</a>
  </div>
</main>
@endsection
