<nav x-data="{ open: false }" class="relative z-20 border-b border-white/10 bg-[#120D1F]/95 text-[#F8FAFC] shadow-[0_8px_28px_rgba(0,0,0,0.22)] backdrop-blur-xl">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->isAdmin() ? route('users.index') : (Auth::user()->isProfessor() ? route('professor.dashboard') : route('student.dashboard')) }}" class="rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-4 focus-visible:ring-offset-[#120D1F]">
                        <x-application-logo class="block h-9 w-auto" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
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

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48" content-classes="py-1 border border-white/10 bg-[#1A132B]">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center rounded-lg border border-transparent px-3 py-2 text-sm font-medium leading-4 text-[#D8CFE8] transition duration-150 ease-in-out hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">
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

                        <!-- Authentication -->
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

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" x-bind:aria-expanded="open.toString()" aria-label="Toggle navigation menu"
                    class="inline-flex items-center justify-center rounded-md p-2 text-[#B8AEC9] transition duration-150 ease-in-out hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400">
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

    <!-- Responsive Navigation Menu -->
    <div :class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
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

        <!-- Responsive Settings Options -->
        <div class="border-t border-white/10 pt-4 pb-1">
            <div class="px-4">
                <div class="text-base font-medium text-[#F8FAFC]">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-[#A59EB8]">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
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
