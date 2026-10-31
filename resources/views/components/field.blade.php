@props(['name', 'label', 'hint' => null, 'type' => 'text'])
<div class="field">
  <label for="{{ $name }}">{{ $label }}</label>
  <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" {{ $attributes }} @error($name) aria-invalid="true" @enderror>
  @if ($hint)<p class="field__hint">{{ $hint }}</p>@endif
  @error($name)<p class="field__error">{{ $message }}</p>@enderror
</div>
