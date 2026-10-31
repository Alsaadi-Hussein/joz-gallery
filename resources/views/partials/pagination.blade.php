@if ($paginator->hasPages())
  <nav class="pager" aria-label="{{ __('shop.a11y.pagination') }}">
    @if ($paginator->onFirstPage())
      <span class="pager__step is-disabled"><x-ico name="arrow" class="ico-dir ico-flip" /><span class="sr-only">{{ __('shop.listing.previous') }}</span></span>
    @else
      <a class="pager__step" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-ico name="arrow" class="ico-dir ico-flip" /><span class="sr-only">{{ __('shop.listing.previous') }}</span></a>
    @endif

    <ul class="pager__pages">
      @foreach ($elements as $element)
        @if (is_string($element))
          <li><span class="pager__gap">{{ $element }}</span></li>
        @else
          @foreach ($element as $page => $url)
            <li>
              @if ($page == $paginator->currentPage())
                <span class="pager__page is-current" aria-current="page">{{ $page }}</span>
              @else
                <a class="pager__page" href="{{ $url }}">{{ $page }}</a>
              @endif
            </li>
          @endforeach
        @endif
      @endforeach
    </ul>

    @if ($paginator->hasMorePages())
      <a class="pager__step" href="{{ $paginator->nextPageUrl() }}" rel="next"><x-ico name="arrow" class="ico-dir" /><span class="sr-only">{{ __('shop.listing.next') }}</span></a>
    @else
      <span class="pager__step is-disabled"><x-ico name="arrow" class="ico-dir" /><span class="sr-only">{{ __('shop.listing.next') }}</span></span>
    @endif
  </nav>
@endif
