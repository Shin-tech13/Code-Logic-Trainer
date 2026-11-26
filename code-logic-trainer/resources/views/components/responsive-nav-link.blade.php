@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex min-h-11 w-full items-center border-l-2 border-accent-400 bg-accent-400/10 py-2 ps-3 pe-4 text-start text-base font-medium text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 transition-colors duration-quick'
            : 'flex min-h-11 w-full items-center border-l-2 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-ink-muted hover:border-white/20 hover:bg-white/5 hover:text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 transition-colors duration-quick';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
