@props(['status'])

@if ($status)
    <div role="status" aria-live="polite" {{ $attributes->merge(['class' => 'rounded-[var(--radius-control)] border border-emerald-300/15 bg-emerald-400/5 px-4 py-3 text-sm font-medium leading-5 text-emerald-200']) }}>
        {{ $status }}
    </div>
@endif
