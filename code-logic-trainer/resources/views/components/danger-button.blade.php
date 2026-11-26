<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-[var(--radius-control)] border border-rose-300/30 bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition-[background-color,border-color] duration-base ease-craft-out hover:border-rose-200/50 hover:bg-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-300 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-850 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
