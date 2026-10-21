<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-6 space-y-6"
        x-data="{
            status: '{{ $session->status }}',
            participants: [],
            quizCode: '{{ $session->quiz_code }}',
            _timer: null,
            _poll() {
                fetch('{{ route('professor.sessions.status', $session->id) }}', {
                    headers: { 'Accept': 'application/json' }
                })
                .then(r => r.json())
                .then(data => {
                    this.status = data.status;
                    this.participants = data.participants;
                    this.quizCode = data.quiz_code;
                })
                .catch(() => {});
            },
            init() {
                this._poll();
                this._timer = setInterval(() => this._poll(), 3000);
            },
            async startQuiz() {
                const resp = await fetch('{{ route('professor.sessions.start', $session->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const data = await resp.json();
                if (data.success) {
                    this.status = 'in_progress';
                    window.location.reload();
                }
            },
            endQuiz() {
                if (!confirm('End this session?')) return;
                document.getElementById('end-form').submit();
            }
        }">

        {{-- Big code display --}}
        <div class="bg-white shadow rounded-lg p-8 text-center">
            <p class="text-sm text-gray-500 mb-2">Quiz Code</p>
            <p class="text-5xl font-extrabold tracking-widest text-indigo-600" x-text="quizCode"></p>
            <p class="text-sm text-gray-500 mt-4">{{ $session->quiz->title }}</p>

            {{-- Status badge --}}
            <div class="mt-4">
                <template x-if="status === 'waiting'">
                    <span class="inline-block bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-semibold">Waiting for students…</span>
                </template>
                <template x-if="status === 'in_progress'">
                    <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">In Progress</span>
                </template>
                <template x-if="status === 'completed'">
                    <span class="inline-block bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold">Completed</span>
                </template>
            </div>
        </div>

        {{-- Controls --}}
        <div class="bg-white shadow rounded-lg p-6 text-center">
            <template x-if="status === 'waiting'">
                <button @click="startQuiz"
                    class="bg-green-600 text-white px-8 py-3 rounded-md hover:bg-green-700 font-bold text-lg">
                    Official Start Quiz
                </button>
            </template>
            <template x-if="status === 'in_progress'">
                <button @click="endQuiz"
                    class="bg-red-600 text-white px-8 py-3 rounded-md hover:bg-red-700 font-bold text-lg">
                    End Session
                </button>
            </template>
            <p class="text-sm text-gray-500 mt-3">
                Students can join at any time — even after the official start.
            </p>
        </div>

        {{-- Participant list --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                Participants (<span x-text="participants.length"></span>)
            </h3>

            <template x-if="participants.length === 0">
                <p class="text-gray-500">No students have joined yet.</p>
            </template>

            <ul class="divide-y divide-gray-100">
                <template x-for="p in participants" :key="p.id">
                    <li class="py-3 flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-800" x-text="p.name"></p>
                            <p class="text-sm text-gray-500" x-text="p.email"></p>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-gray-600" x-text="p.status"></span>
                            <span class="text-sm text-gray-400 ml-2" x-text="'Score: ' + p.score"></span>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <form id="end-form" method="POST" action="{{ route('professor.sessions.end', $session->id) }}" class="hidden">
            @csrf
        </form>
    </div>
</x-app-layout>
