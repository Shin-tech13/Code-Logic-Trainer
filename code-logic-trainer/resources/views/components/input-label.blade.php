@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium leading-5 text-ink-soft']) }}>
    {{ $value ?? $slot }}
</label>
