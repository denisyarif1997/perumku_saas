@props(['title' => config('app.name', 'HousingHub')])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    <meta name="color-scheme" content="light dark">
    

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script>
    // Default awal selalu mode terang.
    // Dark hanya digunakan jika user sebelumnya memilihnya.
    (function () {
        var t = localStorage.getItem('theme');

        if (t === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    })();
</script>

    <style>
        /* fix untuk notch iphone */
       .pt-safe { padding-top: env(safe-area-inset-top); }
       .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
        body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="bg-[#F6F8F7] text-[#1E293B] antialiased">
<div class="mx-auto flex min-h-dvh w-full max-w-md flex-col">
    <header class="sticky top-0 z-30 border-b border-white/60 bg-[#F6F8F7]/80 pt-safe backdrop-blur-xl">
        <div class="flex h-16 items-center justify-between px-5">
            <a href="{{ route('resident.dashboard') }}" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 shadow-lg shadow-teal-600/30">
                    <i data-lucide="home" class="h-5 w-5 text-white"></i>
                </div>
                <p class="font-bold tracking-tight text-[#134E4A]">{{ config('app.name', 'HousingHub') }}</p>
            </a>
            <div class="flex items-center gap-2">
                <livewire:notifications />
                <button type="button" data-theme-toggle
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-[#134E4A] shadow-sm transition active:scale-95"
                    aria-label="Ganti tema">
                    <i data-lucide="moon" class="h-5 w-5 hidden dark:block"></i>
                    <i data-lucide="sun" class="h-5 w-5 block dark:hidden"></i>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Keluar"
                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-red-600 shadow-sm transition active:scale-95">
                        <i data-lucide="log-out" class="h-5 w-5"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 px-5 pb-32 pt-5">{{ $slot }}</main>

    @php
        $navItems = [
            ['resident.dashboard', 'home', 'Home', 'resident.dashboard'],
            ['resident.ipl.index', 'wallet', 'Iuran', 'resident.ipl.*'],
            ['resident.chess.index', 'crown', 'Catur', 'resident.chess.*'],
            ['resident.forum.index', 'messages-square', 'Forum', 'resident.forum.*'],
            ['resident.complaints.index', 'wrench', 'Aduan', 'resident.complaints.*'],
            ['resident.info.index', 'megaphone', 'Info', 'resident.info.*'],
            ['resident.profile', 'user', 'Profil', 'resident.profile'],
        ];
    @endphp

    <nav class="fixed inset-x-0 bottom-0 z-30 pb-safe">
        <div class="mx-auto max-w-md px-4 pb-4">
            <div class="no-scrollbar flex gap-1 overflow-x-auto rounded-[24px] bg-white/95 px-2 py-1.5 shadow-[0_12px_40px_-8px_rgba(19,78,74,0.25)] backdrop-blur-xl">
                @foreach ($navItems as [$route, $icon, $label, $pattern])
                    @php $active = request()->routeIs($pattern); @endphp
                    <a href="{{ route($route) }}"
                        class="flex min-h-[62px] w-[64px] shrink-0 flex-col items-center justify-center gap-1 rounded-2xl text-[10px] tracking-wide transition {{ $active ? 'bg-teal-50 font-semibold text-teal-700' : 'font-medium text-[#94A3B8] hover:text-teal-700' }}">
                        <span class="flex h-8 w-10 items-center justify-center rounded-xl transition {{ $active ? 'bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-md shadow-teal-600/30' : '' }}">
                            <i data-lucide="{{ $icon }}" class="h-[20px] w-[20px]"></i>
                        </span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </nav>
</div>

@livewireScripts
<script>
    document.addEventListener('DOMContentLoaded', () => lucide.createIcons({ attrs: { 'stroke-width': 1.75 } }));
    // buat livewire navigate
    document.addEventListener('livewire:navigated', () => lucide.createIcons({ attrs: { 'stroke-width': 1.75 } }));
</script>
</body>
</html>