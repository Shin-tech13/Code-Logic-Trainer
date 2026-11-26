<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
        <a href="{{ route('student.challenges.index') }}" class="app-action-button inline-flex items-center gap-2 rounded-[var(--radius-control)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-300">
            &larr; All challenges
        </a>

        <header class="app-glass-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-ink-text">{{ $challenge->title }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-ink-subtle sm:text-base">{{ $challenge->description }}</p>
                </div>
                <span class="app-filter-chip">
                    {{ ucfirst($challenge->difficulty) }} · PHP
                </span>
            </div>
        </header>

        <section x-data="challengeRunner()" class="grid gap-5 lg:grid-cols-2">
            <div class="app-tile overflow-hidden">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                    <h2 class="font-semibold text-ink-text">Your PHP solution</h2>
                    <span class="text-xs text-ink-subtle">PHP 8.3</span>
                </div>
                <label class="sr-only" for="challenge-code">PHP solution code</label>
                <textarea id="challenge-code" x-model="code" spellcheck="false" autocomplete="off"
                    class="min-h-[28rem] w-full resize-y border-0 bg-ink-950/80 p-4 font-mono text-sm leading-6 text-emerald-200 outline-none focus:ring-2 focus:ring-inset focus:ring-accent-400"
                    aria-describedby="editor-help"></textarea>
                <p id="editor-help" class="px-4 pb-4 text-xs leading-5 text-ink-subtle">
                    Complete the function shown above. Runs are isolated, time-limited, and have no network access.
                </p>
            </div>

            <div class="space-y-5">
                <div class="app-glass-panel p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold tracking-tight text-ink-text">Automated tests</h2>
                        <button type="button" @click="runTests()" :disabled="running"
                            class="auth-primary-button min-h-11 px-4 py-2 text-sm font-semibold disabled:cursor-wait disabled:opacity-60">
                            <span x-text="running ? 'Running…' : 'Run tests'"></span>
                        </button>
                    </div>

                    <div class="mt-4 space-y-2" aria-live="polite">
                        <template x-for="(test, index) in results" :key="index">
                            <div class="flex items-center justify-between rounded border px-3 py-2 text-sm"
                                :class="test.passed ? 'border-emerald-300/20 bg-emerald-300/10 text-emerald-100' : 'border-rose-300/20 bg-rose-300/10 text-rose-100'">
                                <span x-text="test.label"></span>
                                <span class="font-bold" x-text="test.passed ? 'PASS' : 'FAIL'"></span>
                            </div>
                        </template>
                        <p x-show="!results.length && !running" class="text-sm leading-6 text-ink-subtle">
                            Run your code to check it against the challenge tests.
                        </p>
                        <p x-show="error" x-text="error" class="rounded-[var(--radius-control)] border border-rose-300/20 bg-rose-300/10 p-3 text-sm leading-5 text-rose-100" role="alert"></p>
                    </div>
                </div>

                <div class="app-tile p-5">
                    <h2 class="font-bold text-ink-text">Terminal output</h2>
                    <pre class="mt-3 min-h-24 whitespace-pre-wrap break-words font-mono text-sm leading-6 text-ink-soft" x-text="output || '$ Ready for your solution.'"></pre>
                </div>

                <div x-show="badge" x-cloak class="rounded-[var(--radius-control)] border border-amber-300/20 bg-amber-300/10 p-5 text-amber-100" role="status">
                    <p class="text-lg font-bold" x-text="badgeUnlocked ? '🏅 Badge unlocked!' : 'All tests passed!'"></p>
                    <p class="mt-1" x-text="badge"></p>
                </div>
                @if ($completed)
                    <div class="rounded-[var(--radius-control)] border border-amber-300/20 bg-amber-300/10 p-5 text-amber-100">
                        <p class="font-bold">Previously unlocked badge</p>
                        <p>{{ $challenge->badge_name }}</p>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script>
        function challengeRunner() {
            return {
                code: @js($challenge->starter_code),
                running: false,
                results: [],
                output: '',
                error: '',
                badge: '',
                badgeUnlocked: false,
                async runTests() {
                    if (this.running) return;
                    this.running = true;
                    this.results = [];
                    this.output = '';
                    this.error = '';
                    this.badge = '';
                    this.badgeUnlocked = false;

                    try {
                        const response = await fetch(@js(route('student.challenges.run', $challenge)), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': @js(csrf_token()),
                            },
                            body: JSON.stringify({ code: this.code }),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || `Request failed (${response.status})`);
                        }

                        this.results = data.results || [];
                        this.output = data.output || (data.passed ? 'All tests passed.' : 'Some tests failed.');
                        if (data.passed) {
                            this.badge = data.badge || '';
                            this.badgeUnlocked = data.badge_unlocked;
                            if (data.badge_unlocked) this.celebrate();
                        }
                    } catch (exception) {
                        this.error = exception.message || 'The challenge could not be run.';
                        this.output = this.error;
                    } finally {
                        this.running = false;
                    }
                },
                celebrate() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    const colors = ['#4f46e5', '#16a34a', '#eab308', '#db2777'];
                    for (let index = 0; index < 70; index++) {
                        const piece = document.createElement('span');
                        piece.className = 'challenge-confetti';
                        piece.style.left = `${Math.random() * 100}vw`;
                        piece.style.backgroundColor = colors[index % colors.length];
                        piece.style.animationDelay = `${Math.random() * 500}ms`;
                        document.body.appendChild(piece);
                        window.setTimeout(() => piece.remove(), 2300);
                    }
                },
            };
        }
    </script>
    <style>
        .challenge-confetti {
            position: fixed;
            top: -1rem;
            z-index: 1000;
            width: 0.55rem;
            height: 0.8rem;
            pointer-events: none;
            animation: challenge-fall 1.8s ease-in forwards;
        }

        @keyframes challenge-fall {
            to {
                transform: translate3d(2rem, 105vh, 0) rotate(720deg);
                opacity: 0;
            }
        }
    </style>
</x-app-layout>
