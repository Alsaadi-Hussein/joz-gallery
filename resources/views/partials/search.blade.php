<dialog class="search-sheet" data-dialog="search" aria-label="{{ __('shop.nav.search') }}">
  <form class="search-sheet__form" role="search" action="{{ shop_route('search') }}" method="get" data-search-form>
    <x-ico name="search" />
    <label class="sr-only" for="q">{{ __('shop.nav.search') }}</label>
    <input id="q" name="q" type="search" autocomplete="off" placeholder="{{ __('shop.search.placeholder') }}" data-search-input>
    <button class="icon-btn" type="button" data-close aria-label="{{ __('shop.a11y.close') }}"><x-ico name="close" /></button>
  </form>
  <div class="search-sheet__body">
    <div data-search-popular>
      <p class="eyebrow">{{ __('shop.search.popular') }}</p>
      <div class="chips">
        @foreach ($menuSections as $section)
          <button type="button" class="chip" data-search-term="{{ $section->localized('name') }}">{{ $section->localized('name') }}</button>
        @endforeach
      </div>
    </div>
    <ul class="search-results" data-search-results></ul>
    <p class="search-empty" data-search-empty hidden>{{ __('shop.search.empty') }}</p>
  </div>
</dialog>
