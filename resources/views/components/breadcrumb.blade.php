@props(['items' => []])
<nav class="crumbs" aria-label="{{ __('shop.a11y.breadcrumb') }}">
  <ol>
    <li><a href="{{ shop_route('home') }}">{{ __('shop.nav.home') }}</a></li>
    @foreach ($items as $label => $url)
      <li>
        <x-ico name="arrow" class="ico-dir" />
        @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif
      </li>
    @endforeach
  </ol>
</nav>
