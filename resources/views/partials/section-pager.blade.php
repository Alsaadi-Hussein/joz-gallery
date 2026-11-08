@if ($nav->previous && $nav->next)
  <nav class="sec-pager" aria-label="{{ __('shop.sections_nav.all') }}">
    <a class="sec-pager__link sec-pager__link--prev" href="{{ $nav->previous['url'] }}" data-nav-dir="prev">
      <x-ico name="arrow" class="ico-dir ico-flip" />
      <span><small>{{ __('shop.sections_nav.previous') }}</small>{{ $nav->previous['label'] }}</span>
    </a>
    <a class="sec-pager__link sec-pager__link--next" href="{{ $nav->next['url'] }}" data-nav-dir="next" data-auto-next>
      <span><small>{{ __('shop.sections_nav.next') }}</small>{{ $nav->next['label'] }}</span>
      <x-ico name="arrow" class="ico-dir" />
      <i class="sec-pager__progress" aria-hidden="true"></i>
    </a>
  </nav>
  <p class="sec-auto-hint"><x-ico name="arrow" class="ico-down" />{{ __('shop.sections_nav.auto_hint') }}</p>
@endif
