@props(['endsAt'])
<div {{ $attributes->merge(['class' => 'countdown']) }} data-countdown="{{ $endsAt->toIso8601String() }}">
  <p class="countdown__label">{{ __('shop.countdown.label') }}</p>
  <div class="countdown__units">
    @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $unit => $label)
      <span class="countdown__unit"><b data-unit="{{ $unit }}">00</b><small>{{ __('shop.countdown.'.$label) }}</small></span>
    @endforeach
  </div>
</div>
