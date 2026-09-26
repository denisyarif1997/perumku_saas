@props(['title' => config('app.name', 'HousingHub')])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} — {{ config('app.name', 'HousingHub') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        // Terapkan tema sebelum render untuk mencegah flash (FOUC).
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="bg-[#F6F8F7] text-[#1E293B]">
<div x-data="{ sidebar: false }" class="min-h-dvh lg:flex lg:items-stretch">
    <aside class="hidden w-64 shrink-0 flex-col border-r border-[#EEF2F1] bg-white lg:sticky lg:top-0 lg:flex lg:h-dvh">
        <div class="flex items-center gap-3 px-5 pb-5 pt-6">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 shadow-lg shadow-teal-600/30">
                <i data-lucide="home" class="h-5 w-5 text-white"></i>
            </div>
            <p class="font-bold text-[#134E4A]">{{ config('app.name', 'HousingHub') }}</p>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-6 text-[14px]">
            @php
                $navLink = 'flex items-center gap-3 rounded-xl px-3 py-2.5 transition';
                $navIdle = 'hover:bg-teal-50 hover:text-teal-800';
                $navGroup = 'px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wide text-[#64748B]';
                $sections = \App\Support\AdminMenu::forUser(auth()->user());
            @endphp

            @foreach ($sections as $section)
                @if ($section['label'])
                    <p class="{{ $navGroup }}">{{ $section['label'] }}</p>
                @endif
                @foreach ($section['items'] as [$route, $icon, $label, $pattern])
                    <a href="{{ route($route) }}" wire:navigate
                        class="{{ $navLink }} {{ request()->routeIs($pattern) ? 'bg-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30' : $navIdle }}">
                        <i data-lucide="{{ $icon }}" class="h-4 w-4"></i> {{ $label }}
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>

    {{-- Drawer mobile --}}
    <div x-show="sidebar" x-cloak class="fixed inset-0 z-50 lg:hidden">
        <div @click="sidebar=false" class="absolute inset-0 bg-black/40"></div>
        <aside x-show="sidebar" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
            class="absolute inset-y-0 left-0 flex w-72 flex-col bg-white">
            <div class="flex items-center justify-between px-5 pb-4 pt-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 shadow-lg shadow-teal-600/30">
                        <i data-lucide="home" class="h-5 w-5 text-white"></i>
                    </div>
                    <p class="font-bold text-[#134E4A]">{{ config('app.name', 'HousingHub') }}</p>
                </div>
                <button @click="sidebar=false" class="flex h-11 w-11 items-center justify-center rounded-xl border">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-8 text-[14px]">
                @foreach ($sections as $section)
                    @if ($section['label'])
                        <p class="{{ $navGroup }}">{{ $section['label'] }}</p>
                    @endif
                    @foreach ($section['items'] as [$route, $icon, $label, $pattern])
                        <a href="{{ route($route) }}" wire:navigate @click="sidebar=false"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ request()->routeIs($pattern) ? 'bg-teal-700 font-semibold text-white shadow-lg shadow-teal-700/30' : $navIdle }}">
                            <i data-lucide="{{ $icon }}" class="h-4 w-4"></i> {{ $label }}
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>
    </div>
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 border-b border-[#EEF2F1] bg-[#F6F8F7]/85 backdrop-blur-xl">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center gap-3 px-4 lg:px-8">
                <button @click="sidebar=true" class="flex h-11 w-11 items-center justify-center rounded-xl border lg:hidden"><i data-lucide="menu" class="h-5 w-5"></i></button>
                <h1 class="flex-1 truncate text-lg font-bold">{{ $title }}</h1>
                <div class="flex items-center gap-2">
                    <livewire:notifications variant="admin" />
                    <button type="button" data-theme-toggle
                        class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E2E8F0] transition hover:bg-slate-100"
                        aria-label="Ganti tema">
                        <i data-lucide="moon" class="h-5 w-5 hidden dark:block"></i>
                        <i data-lucide="sun" class="h-5 w-5 block dark:hidden"></i>
                    </button>
                    <livewire:logout />
                </div>
            </div>
        </header>
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 pb-24 pt-6 lg:px-8 lg:pb-10 lg:pt-8">{{ $slot }}</main>
    </div>
</div>
@livewireScripts
</body>
</html>
