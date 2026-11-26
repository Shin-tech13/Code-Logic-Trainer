<nav x-data="{ open: false }"
    class="relative z-20 border-b border-white/10 bg-ink-850/95 text-ink-text shadow-[0_8px_28px_rgba(0,0,0,0.22)] backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->isAdmin() ? route('users.index') : (Auth::user()->isProfessor() ? route('professor.dashboard') : route('student.dashboard')) }}"
                        class="rounded-[var(--radius-control)] transition-opacity duration-quick hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-4 focus-visible:ring-offset-ink-850">
                        <x-application-logo class="block h-8 w-auto sm:h-9" />
                    </a>
                </div>

                <div class="hidden gap-1 sm:-my-px sm:ms-8 sm:flex lg:ms-10">
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*') || request()->routeIs('admin.users.*')">
                            {{ __('Users') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.users.create')" :active="request()->routeIs('admin.users.create')">
                            {{ __('Add user') }}
                        </x-nav-link>
                    @elseif (Auth::user()->isProfessor())
                        <x-nav-link :href="route('professor.dashboard')" :active="request()->routeIs('professor.dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        <x-nav-link :href="route('professor.students.index')" :active="request()->routeIs('professor.students.*')">
                            {{ __('Managed students') }}
                        </x-nav-link>
                        <x-nav-link :href="route('professor.whitelist.index')" :active="request()->routeIs('professor.whitelist.*')">
                            {{ __('Whitelist') }}
                        </x-nav-link>
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                            {{ __('User directory') }}
                        </x-nav-link>
                    @else
                        <x-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                            {{ __('Practice') }}
                        </x-nav-link>
                        <x-nav-link :href="route('student.history')" :active="request()->routeIs('student.history')">
                            {{ __('Quiz history') }}
                        </x-nav-link>
                        <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                            {{ __('My profile') }}
                        </x-nav-link>
                        <x-nav-link :href="route('student.challenges.index')" :active="request()->routeIs('student.challenges.*')">
                            {{ __('Coding Challenges') }}
                        </x-nav-link>
                        <x-nav-link :href="route('student.live.join')" :active="request()->routeIs('student.live.*')">
                            {{ __('Join Live Quiz') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48"
                    content-classes="py-1.5 border border-white/10 rounded-[var(--radius-control)] bg-ink-750/95 shadow-[0_16px_36px_-18px_rgba(0,0,0,0.8)] backdrop-blur-xl">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center rounded-[var(--radius-control)] border border-transparent px-3 py-2 text-sm font-medium leading-4 text-ink-muted transition-colors duration-quick hover:border-white/10 hover:bg-white/5 hover:text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" x-bind:aria-expanded="open.toString()"
                    aria-label="Toggle navigation menu"
                    class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-[var(--radius-control)] p-2 text-ink-muted transition-colors duration-quick hover:bg-white/5 hover:text-ink-text focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{ 'block': open, 'hidden': !open }"
        class="hidden border-t border-white/8 bg-ink-850/95 px-4 pb-3 pt-2 sm:hidden">
        <div class="space-y-1">
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*') || request()->routeIs('admin.users.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.users.create')" :active="request()->routeIs('admin.users.create')">
                    {{ __('Add user') }}
                </x-responsive-nav-link>
            @elseif (Auth::user()->isProfessor())
                <x-responsive-nav-link :href="route('professor.dashboard')" :active="request()->routeIs('professor.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('professor.students.index')" :active="request()->routeIs('professor.students.*')">
                    {{ __('Managed students') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('professor.whitelist.index')" :active="request()->routeIs('professor.whitelist.*')">
                    {{ __('Whitelist') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    {{ __('User directory') }}
                </x-responsive-nav-link>
            @else
                <x-responsive-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                    {{ __('Practice') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('student.history')" :active="request()->routeIs('student.history')">
                    {{ __('Quiz history') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                    {{ __('My profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('student.challenges.index')" :active="request()->routeIs('student.challenges.*')">
                    {{ __('Coding Challenges') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('student.live.join')" :active="request()->routeIs('student.live.*')">
                    {{ __('Join Live Quiz') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <div class="border-t border-white/10 px-1 pb-1 pt-4">
            <div class="px-4">
                <div class="text-base font-medium text-[#F8FAFC]">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-[#A59EB8]">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
