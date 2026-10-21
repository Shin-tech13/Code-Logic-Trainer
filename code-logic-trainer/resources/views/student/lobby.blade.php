<x-app-layout>
    <div class="max-w-3xl mx-auto py-10 px-6 space-y-6"
        x-data="{
            status: '{{ $session->status }}',
            participants: [],
            quizCode: '{{ $session->quiz_code }}',
            quizTitle: '{{ $session->quiz->title }}',
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

                    if (data.status === 'in_progress') {
                        window.location.href = '{{ route('student.live.take', $session->id) }}';
                    }
                })
                .catch(() => {});
            },
            init() {
                this._poll();
                this._timer = setInterval(() => this._poll(), 3000);
            }
        }">

        <div class="bg-white shadow rounded-lg p-8 text-center">
            <p class="text-sm text-gray-500 mb-2">Waiting room</p>
            <h2 class="text-2xl font-bold text-gray-800 mb-1" x-text="quizTitle"></h2>
            <p class="text-sm text-gray-500 mb-4">Quiz Code: <span class="font-bold tracking-widest" x-text="quizCode"></span></p>

            <template x-if="status === 'waiting'">
                <span class="inline-block bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-semibold">
                    Waiting for the professor to start…
                </span>
            </template>
            <template x-if="status === 'in_progress'">
                <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                    Quiz starting…
                </span>
            </template>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                Participants (<span x-text="participants.length"></span>)
            </h3>
            <ul class="divide-y divide-gray-100">
                <template x-for="(p, i) in participants" :key="i">
                    <li class="py-3 flex justify-between items-center">
                        <p class="font-medium text-gray-800" x-text="p.name"></p>
                        <span class="text-sm font-semibold text-gray-600" x-text="p.status"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</x-app-layout>
