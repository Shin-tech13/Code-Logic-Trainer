<x-app-layout>
    <div class="max-w-md mx-auto py-10 px-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Join a Live Quiz</h2>
            <p class="text-sm text-gray-500 mb-6">Enter the quiz code provided by your professor.</p>

            <form method="POST" action="{{ route('student.live.join.store') }}" class="space-y-4">
                @csrf

                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                        {{ $errors->first('quiz_code') }}
                    </div>
                @endif

                <div>
                    <label for="quiz_code" class="block font-medium text-gray-700">Quiz Code</label>
                    <input type="text" id="quiz_code" name="quiz_code" required
                        class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-center text-2xl tracking-widest uppercase"
                        placeholder="QZ-1234">
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-semibold">
                    Join
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
