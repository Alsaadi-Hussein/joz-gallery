{{-- Without JavaScript the sections do not run on, so these links lead on instead. --}}
@if ($nav->previous && $nav->next)
  <nav class="sec-pager" aria-label="{{ __('shop.sections_nav.all') }}" data-section-pager>
    <a class="sec-pager__link sec-pager__link--prev" href="{{ $nav->previous['url'] }}" data-nav-dir="prev">
      <x-ico name="arrow" class="ico-dir ico-flip" />
      <span><small>{{ __('shop.sections_nav.previous') }}</small>{{ $nav->previous['label'] }}</span>
    </a>
    <a class="sec-pager__link sec-pager__link--next" href="{{ $nav->next['url'] }}" data-nav-dir="next">
      <span><small>{{ __('shop.sections_nav.next') }}</small>{{ $nav->next['label'] }}</span>
      <x-ico name="arrow" class="ico-dir" />
    </a>
  </nav>
@endif
