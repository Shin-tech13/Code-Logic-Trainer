@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex min-h-11 items-center border-b-2 border-accent-400 px-3 pt-1 text-sm font-medium leading-5 text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 transition-colors duration-quick'
            : 'inline-flex min-h-11 items-center border-b-2 border-transparent px-3 pt-1 text-sm font-medium leading-5 text-ink-muted hover:border-white/20 hover:text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 transition-colors duration-quick';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
