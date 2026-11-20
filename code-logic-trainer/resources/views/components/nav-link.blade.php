@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-violet-400 text-sm font-medium leading-5 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[#B8AEC9] hover:text-white hover:border-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
