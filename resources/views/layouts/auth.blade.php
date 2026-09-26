<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D9488">
    <title>@yield('title', 'Masuk') — {{ config('app.name', 'HousingHub') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    @livewireStyles
</head>
<body class="relative overflow-x-hidden bg-[#F6F8F7] text-[#1E293B] antialiased">
    {{-- Aksen hijau samar di latar --}}
    <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-teal-400/20 blur-3xl"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-24 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl"></div>

    <div class="relative mx-auto flex min-h-dvh w-full max-w-md flex-col px-5 pb-8 pt-10 md:max-w-lg">
        <div class="mb-8 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-lg shadow-teal-600/30">
                <i data-lucide="home" class="h-5 w-5"></i>
            </div>
            <div>
                <p class="text-lg font-bold leading-tight text-[#134E4A]">{{ config('app.name', 'HousingHub') }}</p>
                <p class="text-[13px] text-[#64748B]">Kelola perumahan dari HP</p>
            </div>
        </div>
        {{ $slot ?? '' }}
        @yield('content')
    </div>
    @livewireScripts
</body>
</html>
