<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10"
        x-data="{
            status: @js($session->status),
            participants: [],
            quizCode: @js($session->quiz_code),
            quizTitle: @js($session->quiz->title),
            _timer: null,
            _poll() {
                fetch('{{ route('student.live.status', $session->id) }}', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    this.status = data.status;
                    this.participants = data.participants;
                    this.quizCode = data.quiz_code;
                    this.quizTitle = data.quiz_title;
                    if (data.status === 'in_progress') window.location.href = '{{ route('student.live.take', $session->id) }}';
                })
                .catch(() => {});
            },
            init() {
                this._poll();
                this._timer = setInterval(() => this._poll(), 3000);
            }
        }">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--cyan" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-3xl space-y-6">
            <section class="app-glass-panel rounded-2xl border p-6 text-center sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Waiting room</p>
                <h1 class="mt-2 text-2xl font-bold text-[#F8FAFC]" x-text="quizTitle"></h1>
                <p class="mt-3 text-sm text-[#A59EB8]">Quiz code:
                    <span class="font-mono font-bold tracking-widest text-violet-100" x-text="quizCode"></span>
                </p>

                <template x-if="status === 'waiting'">
                    <p class="mt-5 inline-flex items-center rounded-full border border-amber-300/25 bg-amber-300/10 px-3 py-1 text-sm font-semibold text-amber-100" role="status">
                        Waiting for the professor to start
                    </p>
                </template>
                <template x-if="status === 'in_progress'">
                    <p class="mt-5 inline-flex items-center rounded-full border border-emerald-300/25 bg-emerald-300/10 px-3 py-1 text-sm font-semibold text-emerald-100" role="status">
                        Quiz starting
                    </p>
                </template>
            </section>

            <section class="app-glass-panel rounded-2xl border p-5 sm:p-6">
                <h2 class="text-lg font-bold text-[#F8FAFC]">
                    Participants (<span x-text="participants.length"></span>)
                </h2>
                <ul class="mt-3 divide-y divide-white/[0.08]">
                    <template x-for="(p, i) in participants" :key="i">
                        <li class="flex items-center justify-between gap-3 py-3">
                            <p class="font-medium text-[#E8E2F0]" x-text="p.name"></p>
                            <span class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold text-[#C8BED6]" x-text="p.status"></span>
                        </li>
                    </template>
                </ul>
                <p x-show="participants.length === 0" class="mt-3 text-sm text-[#A59EB8]">You are in the session. Other participants will appear here.</p>
            </section>
        </main>
    </div>
</x-app-layout>
