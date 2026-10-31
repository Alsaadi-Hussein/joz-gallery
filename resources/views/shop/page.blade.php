@extends('layouts.app')

@php
    $title = $page->localized('title');
@endphp

@section('title', $title.' | '.__('shop.meta.title_suffix'))
@if ($page->meta_description)
  @section('description', $page->meta_description)
@endif

@section('content')
<main id="main" class="listing wrap">
  <x-breadcrumb :items="[$title => null]" />

  <article class="prose">
    <h1 class="listing__title">{{ $title }}</h1>
    {!! str($page->localized('body'))->sanitizeHtml() !!}
  </article>
</main>
@endsection
