@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'min-h-11 rounded-[var(--radius-field)] border border-white/10 bg-ink-950/40 px-3 text-sm text-ink-text placeholder:text-ink-subtle shadow-sm transition-[border-color,box-shadow] duration-quick focus:border-accent-400 focus:ring-2 focus:ring-accent-400/30 disabled:cursor-not-allowed disabled:opacity-55']) }}>
