<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-xl border border-transparent bg-gradient-to-r from-violet-600 to-purple-800 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-950/30 transition duration-150 hover:-translate-y-0.5 hover:from-violet-500 hover:to-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:ring-offset-2 active:scale-[0.98]']) }}>
    {{ $slot }}
</button>
