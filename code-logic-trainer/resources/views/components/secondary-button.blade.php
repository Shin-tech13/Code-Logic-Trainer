<button {{ $attributes->merge(['type' => 'button', 'class' => 'app-secondary-button inline-flex min-h-11 items-center justify-center px-4 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-850 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
