@props(['status'])

@if ($status)
    <div role="status" aria-live="polite" {{ $attributes->merge(['class' => 'font-medium text-sm']) }}>
        {{ $status }}
    </div>
@endif
