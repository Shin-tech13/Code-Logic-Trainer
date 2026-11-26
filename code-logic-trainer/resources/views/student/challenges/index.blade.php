<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 sm:py-10">
        <header>
            <h1 class="text-3xl font-bold tracking-tight text-ink-text sm:text-4xl">Coding challenges</h1>
            <p class="mt-2 text-sm leading-6 text-ink-subtle sm:text-base">Write PHP solutions, run private test cases, and earn badges.</p>
        </header>

        <div class="grid gap-5 sm:grid-cols-2">
            @forelse ($challenges as $challenge)
                <a href="{{ route('student.challenges.show', $challenge) }}"
                    class="app-glass-panel block p-5 transition-[border-color,background-color,box-shadow,transform] duration-base hover:-translate-y-0.5 hover:border-accent-300/30 hover:bg-ink-750/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-300 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-xl font-bold tracking-tight text-ink-text">{{ $challenge->title }}</h2>
                        <span class="app-filter-chip">
                            {{ ucfirst($challenge->difficulty) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-ink-subtle">{{ $challenge->description }}</p>
                    <p class="mt-5 text-sm font-semibold {{ $challenge->completed_by_user ? 'text-emerald-200' : 'text-accent-200' }}">
                        {{ $challenge->completed_by_user ? 'Badge unlocked: '.$challenge->badge_name : 'Badge: '.$challenge->badge_name }}
                    </p>
                </a>
            @empty
                <p class="app-glass-panel p-6 text-sm leading-6 text-ink-subtle">No coding challenges are available yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
