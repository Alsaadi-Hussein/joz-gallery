<div class="announce" role="region" aria-label="{{ __('shop.brand') }}">
  <div class="announce__track">
    @foreach ([false, true] as $duplicate)
      <ul class="announce__list" @if ($duplicate) aria-hidden="true" @endif>
        <li><x-ico name="truck" />{{ __('shop.announce.delivery') }}</li>
        <li><x-ico name="cash" />{{ __('shop.announce.cash') }}</li>
        <li><x-ico name="whatsapp" />{{ __('shop.announce.whatsapp', ['phone' => $store['phone']]) }}</li>
      </ul>
    @endforeach
  </div>
</div>
