@extends('layouts.app')

@section('content')
<main id="main" class="home">

  <nav class="highlights" aria-label="{{ __('shop.nav.sections') }}">
    <ul class="highlights__list wrap">
      <li>
        <a class="hl" href="{{ shop_route('picks') }}">
          <span class="hl__ring hl__ring--brand"><img src="{{ asset('assets/img/brand/emblem-cream.png') }}" alt="" width="64" height="64"></span>
          <span class="hl__label">{{ __('shop.nav.picks') }}</span>
        </a>
      </li>
      @foreach ($sections as $section)
        <li>
          <a class="hl" href="{{ shop_route('category', $section) }}">
            <span class="hl__ring"><img src="{{ $section->getFirstMediaUrl('image', 'thumb') }}" alt="" width="64" height="64" loading="lazy"></span>
            <span class="hl__label">{{ $section->localized('name') }}</span>
          </a>
        </li>
      @endforeach
      <li>
        <a class="hl" href="{{ whatsapp_url(__('shop.whatsapp.projects')) }}" target="_blank" rel="noopener noreferrer">
          <span class="hl__ring hl__ring--wa"><x-ico name="whatsapp" /></span>
          <span class="hl__label">{{ __('shop.projects.short') }}</span>
        </a>
      </li>
    </ul>
  </nav>

  <section class="hero wrap" aria-roledescription="carousel" aria-label="{{ __('shop.brand') }}">
    <div class="hero__slider" data-slider>
      <div class="hero__track" data-track>
        @foreach ($banners as $banner)
          <div class="slide" role="group" aria-roledescription="slide" aria-label="{{ __('shop.a11y.slide', ['number' => $loop->iteration]) }}" data-slide>
            <picture>
              @if ($banner->hasMedia('image_mobile'))
                <source media="(max-width: 700px)" srcset="{{ $banner->getFirstMediaUrl('image_mobile', 'large') }}">
              @endif
              <img src="{{ $banner->getFirstMediaUrl('image', 'large') }}" alt="" width="1600" height="900"
                   @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
            </picture>
            <div class="slide__content">
              @if ($banner->localized('eyebrow'))<p class="slide__eyebrow">{{ $banner->localized('eyebrow') }}</p>@endif
              <{{ $loop->first ? 'h1' : 'h2' }} class="slide__title">{{ $banner->localized('title') }}</{{ $loop->first ? 'h1' : 'h2' }}>
              @if ($banner->localized('subtitle'))<p class="slide__text">{{ $banner->localized('subtitle') }}</p>@endif
              @if ($banner->localized('button'))
                <a class="btn btn--cream" href="{{ shop_link($banner->link) }}">{{ $banner->localized('button') }}<x-ico name="arrow" class="ico-dir" /></a>
              @endif
            </div>
          </div>
        @endforeach
      </div>
      <div class="hero__controls">
        <button class="hero__arrow" type="button" data-prev aria-label="{{ __('shop.a11y.previous') }}"><x-ico name="arrow" class="ico-dir ico-flip" /></button>
        <div class="hero__dots">
          @foreach ($banners as $banner)
            <button type="button" data-dot="{{ $loop->index }}" aria-label="{{ __('shop.a11y.slide', ['number' => $loop->iteration]) }}" @if ($loop->first) aria-current="true" @endif><i></i></button>
          @endforeach
        </div>
        <button class="hero__arrow" type="button" data-next aria-label="{{ __('shop.a11y.next') }}"><x-ico name="arrow" class="ico-dir" /></button>
      </div>
    </div>

    <div class="hero__side">
      <div class="tile tile--logo">
        <img src="{{ asset('assets/img/brand/logo-cream.png') }}" alt="{{ __('shop.brand') }}" width="203" height="198">
      </div>
      <div class="tile tile--tagline">
        <img class="tile__watermark" src="{{ asset('assets/img/brand/emblem-cream.png') }}" alt="" width="265" height="265" loading="lazy">
        <p class="tagline"><span>{{ __('shop.hero.tagline_1') }}</span><span>{{ __('shop.hero.tagline_2') }}</span></p>
      </div>
    </div>
  </section>

  <section class="trust wrap" aria-label="{{ __('shop.trust.label') }}">
    <ul class="trust__list">
      <li><x-ico name="truck" /><div><strong>{{ __('shop.trust.delivery_title') }}</strong><span>{{ __('shop.trust.delivery_text') }}</span></div></li>
      <li><x-ico name="cash" /><div><strong>{{ __('shop.trust.cash_title') }}</strong><span>{{ __('shop.trust.cash_text') }}</span></div></li>
      <li><x-ico name="chat" /><div><strong>{{ __('shop.trust.chat_title') }}</strong><span>{{ __('shop.trust.chat_text') }}</span></div></li>
      <li><x-ico name="sparkle" /><div><strong>{{ __('shop.trust.curated_title') }}</strong><span>{{ __('shop.trust.curated_text') }}</span></div></li>
    </ul>
  </section>

  <section class="block wrap" id="sections" aria-labelledby="sections-title">
    <x-section-title id="sections-title" :title="__('shop.home.sections_title')" :subtitle="__('shop.home.sections_sub')" />
    <ul class="cat-grid">
      @foreach ($sections as $section)
        <li>
          <a class="cat" href="{{ shop_route('category', $section) }}">
            <img src="{{ $section->getFirstMediaUrl('image', 'thumb') }}" alt="" width="640" height="640" loading="lazy" decoding="async">
            <span class="cat__info">
              <span class="cat__name">{{ $section->localized('name') }}</span>
              <span class="cat__count">{{ trans_choice('shop.product_count', $section->products_total) }}<x-ico name="arrow" class="ico-dir" /></span>
            </span>
          </a>
        </li>
      @endforeach
      <li>
        <a class="cat cat--picks" href="{{ shop_route('picks') }}">
          <img src="{{ asset('assets/img/demo/picks.webp') }}" alt="" width="640" height="640" loading="lazy" decoding="async">
          <span class="cat__seal" aria-hidden="true"><img src="{{ asset('assets/img/brand/emblem-cream.png') }}" alt="" width="56" height="56" loading="lazy"></span>
          <span class="cat__info">
            <span class="cat__name">{{ __('shop.nav.picks') }}</span>
            <span class="cat__count">{{ trans_choice('shop.product_count', $picksTotal) }}<x-ico name="arrow" class="ico-dir" /></span>
          </span>
        </a>
      </li>
    </ul>

    <a class="projects" href="{{ whatsapp_url(__('shop.whatsapp.projects')) }}" target="_blank" rel="noopener noreferrer">
      <img class="projects__img" src="{{ asset('assets/img/demo/projects-wide.webp') }}" alt="" width="1200" height="700" loading="lazy" decoding="async">
      <span class="projects__body">
        <span class="eyebrow eyebrow--light">{{ __('shop.projects.eyebrow') }}</span>
        <span class="projects__title">{{ __('shop.projects.title') }}</span>
        <span class="projects__text">{{ __('shop.projects.text') }}</span>
        <span class="btn btn--sage"><x-ico name="whatsapp" />{{ __('shop.projects.cta') }}</span>
      </span>
    </a>
  </section>

  @if ($offerProducts->isNotEmpty())
    <section class="offers" id="offers" aria-labelledby="offers-title">
      <div class="offers__panel wrap">
        <div class="offers__intro">
          <p class="eyebrow eyebrow--light">{{ __('shop.home.offers_eyebrow') }}</p>
          <h2 class="offers__title" id="offers-title">{{ __('shop.home.offers_title') }}</h2>
          <p class="offers__sub">{{ __('shop.home.offers_sub') }}</p>
          <x-countdown :ends-at="$offerEndsAt" />
          <a class="btn btn--cream" href="{{ shop_route('offers') }}">{{ __('shop.home.offers_all') }}<x-ico name="arrow" class="ico-dir" /></a>
        </div>
        <div class="offers__rail" tabindex="0" aria-label="{{ __('shop.home.offers_title') }}">
          @foreach ($offerProducts as $product)
            <x-product-card :product="$product" />
          @endforeach
        </div>
      </div>
    </section>
  @endif

  @if ($bestSellers->isNotEmpty())
    <section class="block wrap" aria-labelledby="best-title">
      <x-section-title id="best-title" :title="__('shop.nav.best_sellers')" :subtitle="__('shop.home.best_sub')" :link="shop_route('best-sellers')" />
      <div class="pgrid">
        @foreach ($bestSellers as $product)
          <x-product-card :product="$product" />
        @endforeach
      </div>
    </section>
  @endif

  <section class="story wrap" aria-labelledby="story-title">
    <figure class="story__media">
      <img src="{{ asset('assets/img/demo/story.webp') }}" alt="" width="1100" height="900" loading="lazy" decoding="async">
      <img class="story__seal" src="{{ asset('assets/img/brand/emblem-walnut.png') }}" alt="" width="120" height="120" loading="lazy">
    </figure>
    <div class="story__text">
      <p class="eyebrow">{{ __('shop.home.story_eyebrow') }}</p>
      <h2 class="story__title" id="story-title">{{ __('shop.slogan') }}</h2>
      <p>{{ __('shop.home.story_text_1') }}</p>
      <p>{{ __('shop.home.story_text_2') }}</p>
      <a class="btn btn--walnut" href="{{ shop_route('page', 'about') }}">{{ __('shop.home.story_cta') }}<x-ico name="arrow" class="ico-dir" /></a>
    </div>
  </section>

  @if ($newArrivals->isNotEmpty())
    <section class="block wrap" aria-labelledby="new-title">
      <x-section-title id="new-title" :title="__('shop.nav.new')" :subtitle="__('shop.home.new_sub')" :link="shop_route('new')" />
      <div class="pgrid">
        @foreach ($newArrivals as $product)
          <x-product-card :product="$product" />
        @endforeach
      </div>
    </section>
  @endif

  <section class="ig wrap" aria-labelledby="ig-title">
    <div class="ig__card">
      <div class="ig__text">
        <div class="ig__profile">
          <span class="ig__avatar"><img src="{{ asset('assets/img/brand/emblem-cream.png') }}" alt="" width="56" height="56" loading="lazy"></span>
          <span><strong dir="ltr">{{ '@'.$store['instagram'] }}</strong><small>{{ __('shop.brand') }}</small></span>
        </div>
        <h2 class="ig__title" id="ig-title">{{ __('shop.home.ig_title') }}</h2>
        <p>{{ __('shop.home.ig_text') }}</p>
        <div class="ig__actions">
          <a class="btn btn--walnut" href="{{ $store['instagram_url'] }}" target="_blank" rel="noopener noreferrer"><x-ico name="instagram" />{{ __('shop.home.ig_follow') }}</a>
          <a class="btn btn--sage" href="{{ whatsapp_url() }}" target="_blank" rel="noopener noreferrer"><x-ico name="whatsapp" />{{ __('shop.whatsapp.chat') }}</a>
        </div>
        <p class="ig__phone" dir="ltr"><x-ico name="phone" />{{ $store['whatsapp'] }}</p>
      </div>
      <ul class="ig__grid">
        @foreach (range(1, 6) as $number)
          <li><a href="{{ $store['instagram_url'] }}" target="_blank" rel="noopener noreferrer" tabindex="-1" aria-hidden="true">
            <img src="{{ asset("assets/img/demo/ig-{$number}.webp") }}" alt="" width="480" height="480" loading="lazy" decoding="async"></a></li>
        @endforeach
      </ul>
    </div>
  </section>
</main>
@endsection
