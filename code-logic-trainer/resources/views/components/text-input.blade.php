@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-gray-300 shadow-sm transition duration-150 focus:border-violet-500 focus:ring-violet-500']) }}>
