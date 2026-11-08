{{-- Every section as a tab; the current one is marked and scrolled into view. --}}
<nav class="sec-tabs" aria-label="{{ __('shop.sections_nav.all') }}">
  <ul class="sec-tabs__list wrap" data-tabs>
    @foreach ($nav->tabs as $tab)
      <li>
        <a @class(['sec-tab', 'sec-tab--picks' => $tab['picks']]) href="{{ $tab['url'] }}" @if ($tab['key'] === $nav->current) aria-current="page" @endif>
          @if ($tab['image'])<img src="{{ $tab['image'] }}" alt="" width="28" height="28" loading="lazy">@endif
          <span>{{ $tab['label'] }}</span>
        </a>
      </li>
    @endforeach
  </ul>
</nav>
