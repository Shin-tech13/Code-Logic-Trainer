<x-app-layout>
    <div class="auth-shell relative isolate min-h-[calc(100vh-4rem)] overflow-hidden px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
        <div class="ambient-orb ambient-orb--violet" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--indigo" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb--plum" aria-hidden="true"></div>

        <main class="relative z-10 mx-auto max-w-3xl space-y-6"
            x-data="{
                rosterMode: @js(old('roster_mode', 'all_whitelisted')),
                makeUpEnabled: @js((bool) old('is_make_up')),
                studentSearch: ''
            }">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold tracking-wide text-accent-300">Live session setup</p>
                    <h1 class="mt-2 break-words text-2xl font-bold tracking-tight text-ink-text sm:text-3xl">{{ $quiz->title }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-subtle">
                        Choose who can join. The roster is saved with this session and does not change if class or whitelist records change later.
                    </p>
                </div>
                <a href="{{ route('professor.quizzes.show', $quiz) }}"
                    class="app-secondary-button inline-flex min-h-11 shrink-0 items-center justify-center px-4 text-sm font-semibold">
                    Back to quiz
                </a>
            </header>

            @if ($errors->any())
                <div class="rounded-[var(--radius-control)] border border-rose-300/25 bg-rose-400/10 px-4 py-3 text-sm text-rose-100"
                    role="alert" aria-labelledby="session-setup-errors">
                    <h2 id="session-setup-errors" class="font-semibold">Review the session settings</h2>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('professor.sessions.launch', $quiz) }}" class="space-y-5">
                @csrf

                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="roster-heading">
                    <div class="mb-5">
                        <h2 id="roster-heading" class="text-lg font-semibold tracking-tight text-ink-text">Who can join?</h2>
                        <p class="mt-1 text-sm leading-6 text-ink-subtle">Pick one source for this session’s eligible students.</p>
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="sr-only">Choose a session roster</legend>

                        <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/35 p-4 transition-colors duration-base hover:border-accent-300/30"
                            :class="rosterMode === 'all_whitelisted' ? 'border-accent-300/40 bg-accent-500/[0.07]' : ''">
                            <input type="radio" name="roster_mode" value="all_whitelisted" x-model="rosterMode"
                                @checked(old('roster_mode', 'all_whitelisted') === 'all_whitelisted')
                                aria-controls="all-whitelisted-details"
                                class="mt-1 size-4 accent-accent-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-300">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink-text">All whitelisted students</span>
                                <span id="all-whitelisted-details" class="mt-1 block text-sm leading-5 text-ink-subtle">
                                    Include your {{ $activeWhitelistCount }} active {{ \Illuminate\Support\Str::plural('whitelist entry', $activeWhitelistCount) }}. The eligible emails are snapshotted when this session is created.
                                </span>
                            </span>
                        </label>

                        <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/35 p-4 transition-colors duration-base hover:border-accent-300/30"
                            :class="rosterMode === 'saved_class' ? 'border-accent-300/40 bg-accent-500/[0.07]' : ''">
                            <input type="radio" name="roster_mode" value="saved_class" x-model="rosterMode"
                                @checked(old('roster_mode') === 'saved_class')
                                aria-controls="saved-class-details"
                                class="mt-1 size-4 accent-accent-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-300">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink-text">Saved class</span>
                                <span class="mt-1 block text-sm leading-5 text-ink-subtle">Use students currently assigned to one of your classes.</span>
                            </span>
                        </label>
                        <div id="saved-class-details" x-show="rosterMode === 'saved_class'" x-cloak x-transition
                            class="ml-7 border-l border-white/10 pl-4 motion-reduce:transition-none">
                            @if ($classrooms->isEmpty())
                                <p class="text-sm leading-5 text-amber-100">
                                    You haven’t created a class yet.
                                    <a href="{{ route('professor.students.index') }}" class="font-semibold text-accent-200 underline decoration-accent-300/50 underline-offset-4">
                                        Create or manage classes
                                    </a>
                                </p>
                            @else
                                <label for="session-classroom" class="auth-label">Class</label>
                                <select id="session-classroom" name="classroom_id"
                                    :required="rosterMode === 'saved_class'" aria-describedby="classroom-help @error('classroom_id') classroom-error @enderror"
                                    class="auth-input mt-2 text-base sm:text-sm">
                                    <option value="">Choose a saved class</option>
                                    @foreach ($classrooms as $classroom)
                                        <option value="{{ $classroom->id }}" @selected((string) old('classroom_id') === (string) $classroom->id)>
                                            {{ $classroom->title }} · {{ $classroom->students_count + $classroom->email_assignments_count }} {{ \Illuminate\Support\Str::plural('roster entry', $classroom->students_count + $classroom->email_assignments_count) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p id="classroom-help" class="mt-2 text-xs leading-5 text-ink-subtle">Students in this class at setup time are saved as the session roster.</p>
                                @error('classroom_id')
                                    <p id="classroom-error" class="mt-2 text-sm text-rose-200" role="alert">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-[var(--radius-control)] border border-white/10 bg-ink-950/35 p-4 transition-colors duration-base hover:border-accent-300/30"
                            :class="rosterMode === 'manual' ? 'border-accent-300/40 bg-accent-500/[0.07]' : ''">
                            <input type="radio" name="roster_mode" value="manual" x-model="rosterMode"
                                @checked(old('roster_mode') === 'manual')
                                aria-controls="manual-roster-details"
                                class="mt-1 size-4 accent-accent-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent-300">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-ink-text">Manual / specific students</span>
                                <span class="mt-1 block text-sm leading-5 text-ink-subtle">Select managed students or whitelist entries, then add other email addresses if needed.</span>
                            </span>
                        </label>
                        <div id="manual-roster-details" x-show="rosterMode === 'manual'" x-cloak x-transition
                            class="ml-7 space-y-4 border-l border-white/10 pl-4 motion-reduce:transition-none">
                            <div>
                                <label for="roster-search" class="auth-label">Find a managed student or whitelist entry</label>
                                <input id="roster-search" type="search" x-model="studentSearch"
                                    autocomplete="off" placeholder="Search by name or email"
                                    class="auth-input mt-2 min-h-11 text-base sm:text-sm">
                            </div>

                            @if ($rosterOptions->isEmpty())
                                <p class="rounded-[var(--radius-control)] border border-white/10 bg-ink-950/35 p-4 text-sm leading-5 text-ink-subtle">
                                    No managed students or active whitelist entries to select. You can still add individual email addresses below.
                                </p>
                            @else
                                <fieldset>
                                    <legend class="mb-2 text-sm font-semibold text-ink-soft">Existing students and whitelist entries</legend>
                                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-[var(--radius-control)] border border-white/10 bg-ink-950/35 p-2 [scrollbar-gutter:stable]">
                                        @foreach ($rosterOptions as $option)
                                            <label data-search="{{ \Illuminate\Support\Str::lower($option['name'].' '.$option['email']) }}"
                                                x-show="!studentSearch || ($el.dataset.search || '').includes(studentSearch.trim().toLowerCase())"
                                                class="flex min-h-11 cursor-pointer items-start gap-3 rounded-[var(--radius-field)] px-3 py-2 hover:bg-white/[0.04]">
                                                <input type="checkbox" name="roster_emails[]" value="{{ $option['email'] }}"
                                                    @checked(in_array($option['email'], (array) old('roster_emails', []), true))
                                                    class="mt-1 size-4 rounded border-white/20 bg-ink-900 text-accent-500 focus-visible:ring-2 focus-visible:ring-accent-300">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block break-all text-sm font-medium text-ink-text">{{ $option['name'] }}</span>
                                                    <span class="block break-all font-mono text-xs text-ink-muted">{{ $option['email'] }}</span>
                                                </span>
                                                <span class="shrink-0 text-right text-xs text-ink-subtle">{{ $option['context'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('roster_emails')
                                        <p class="mt-2 text-sm text-rose-200" role="alert">{{ $message }}</p>
                                    @enderror
                                </fieldset>
                            @endif

                            <div>
                                <label for="manual-emails" class="auth-label">Additional email addresses <span class="font-normal text-ink-subtle">(optional)</span></label>
                                <textarea id="manual-emails" name="manual_emails" rows="4" maxlength="10000"
                                    aria-describedby="manual-emails-help @error('manual_emails') manual-emails-error @enderror"
                                    class="auth-input mt-2 min-h-24 text-base sm:text-sm"
                                    placeholder="student.one@example.edu&#10;student.two@example.edu">{{ old('manual_emails') }}</textarea>
                                <p id="manual-emails-help" class="mt-2 text-xs leading-5 text-ink-subtle">Paste one address per line, or separate addresses with commas or semicolons. Addresses are matched without regard to case.</p>
                                @error('manual_emails')
                                    <p id="manual-emails-error" class="mt-2 text-sm text-rose-200" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </fieldset>
                </section>

                <section class="app-glass-panel p-5 sm:p-6" aria-labelledby="make-up-heading">
                    <h2 id="make-up-heading" class="text-lg font-semibold tracking-tight text-ink-text">Make-up session <span class="text-sm font-normal text-ink-subtle">(optional)</span></h2>
                    <label for="is-make-up" class="mt-4 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-ink-soft">
                        <input id="is-make-up" name="is_make_up" type="checkbox" value="1" x-model="makeUpEnabled"
                            @checked(old('is_make_up'))
                            aria-controls="make-up-label-field"
                            class="size-4 rounded border-white/20 bg-ink-900 text-accent-500 focus-visible:ring-2 focus-visible:ring-accent-300">
                        <span>Mark this as a make-up exam or session</span>
                    </label>
                    <div id="make-up-label-field" x-show="makeUpEnabled" x-cloak x-transition
                        class="mt-4 motion-reduce:transition-none">
                        <label for="make-up-label" class="auth-label">Note or label <span class="font-normal text-ink-subtle">(optional)</span></label>
                        <input id="make-up-label" name="make_up_label" type="text" maxlength="120"
                            value="{{ old('make_up_label') }}" placeholder="For example, Friday make-up"
                            class="auth-input mt-2 text-base sm:text-sm">
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                    <a href="{{ route('professor.quizzes.show', $quiz) }}"
                        class="app-secondary-button inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">
                        Cancel
                    </a>
                    <button type="submit" class="auth-primary-button inline-flex min-h-11 items-center justify-center px-5 text-sm font-semibold">
                        Create live session
                    </button>
                </div>
            </form>
        </main>
    </div>
</x-app-layout>
