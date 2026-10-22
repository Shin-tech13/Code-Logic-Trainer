<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-6 px-6 py-10">
        <header>
            <h1 class="text-3xl font-bold text-gray-900">Coding Challenges</h1>
            <p class="mt-2 text-gray-600">Write PHP solutions, run private test cases, and earn badges.</p>
        </header>

        <div class="grid gap-5 sm:grid-cols-2">
            @forelse ($challenges as $challenge)
                <a href="{{ route('student.challenges.show', $challenge) }}"
                    class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-xl font-bold text-gray-900">{{ $challenge->title }}</h2>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                            {{ ucfirst($challenge->difficulty) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">{{ $challenge->description }}</p>
                    <p class="mt-5 text-sm font-semibold {{ $challenge->completed_by_user ? 'text-green-700' : 'text-indigo-700' }}">
                        {{ $challenge->completed_by_user ? 'Badge unlocked: '.$challenge->badge_name : 'Badge: '.$challenge->badge_name }}
                    </p>
                </a>
            @empty
                <p class="rounded-lg bg-white p-6 text-gray-600 shadow-sm">No coding challenges are available yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
