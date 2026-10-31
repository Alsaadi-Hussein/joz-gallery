@props(['name'])
<svg {{ $attributes->merge(['class' => 'ico']) }} aria-hidden="true" focusable="false"><use href="#i-{{ $name }}"></use></svg>
