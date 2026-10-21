<x-app-layout>
    <div class="max-w-4xl mx-auto py-10 px-6 space-y-8">

        @if (session('status'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('status') }}
            </div>
        @endif

        @if (session('whitelist_errors'))
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach (session('whitelist_errors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Student Whitelist</h2>
            <p class="text-sm text-gray-500 mb-6">Only whitelisted students can log in and join your live quizzes.</p>

            <form method="POST" action="{{ route('professor.whitelist.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-medium text-gray-700">Student Name</label>
                        <input type="text" name="name" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" placeholder="Juan Dela Cruz">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700">Student Email</label>
                        <input type="email" name="email" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" placeholder="student@school.edu">
                    </div>
                </div>

                <div>
                    <label class="block font-medium text-gray-700">Or bulk add (one email per line)</label>
                    <textarea name="bulk_emails" rows="4" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" placeholder="a@school.edu&#10;b@school.edu&#10;c@school.edu"></textarea>
                </div>

                <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 font-semibold">
                    Add to Whitelist
                </button>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Whitelisted Students ({{ $students->count() }})</h3>

            @if ($students->isEmpty())
                <p class="text-gray-500">No students whitelisted yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2">Name</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Account</th>
                            <th class="py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($students as $student)
                            <tr>
                                <td class="py-2 font-medium text-gray-800">{{ $student->name }}</td>
                                <td class="py-2 text-gray-600">{{ $student->email }}</td>
                                <td class="py-2">
                                    @if ($student->user)
                                        <span class="text-green-600">Registered</span>
                                    @else
                                        <span class="text-gray-400">Not yet registered</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" action="{{ route('professor.whitelist.destroy', $student->id) }}"
                                        onsubmit="return confirm('Remove {{ $student->email }} from the whitelist?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
