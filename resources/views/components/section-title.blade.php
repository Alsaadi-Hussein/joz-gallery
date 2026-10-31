@props(['id', 'title', 'subtitle' => null, 'link' => null])
<div class="sec-head">
  <span class="sec-head__mark" aria-hidden="true"><x-ico name="sparkle" /></span>
  <h2 class="sec-head__title" id="{{ $id }}">{{ $title }}</h2>
  @if ($subtitle)<p class="sec-head__sub">{{ $subtitle }}</p>@endif
  @if ($link)<a class="link-arrow" href="{{ $link }}">{{ __('shop.home.view_all') }}<x-ico name="arrow" class="ico-dir" /></a>@endif
</div>
