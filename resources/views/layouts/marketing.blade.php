<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0D9488">
    <meta name="description" content="{{ config('app.name', 'Perumku') }} — aplikasi manajemen perumahan: iuran, kas, warga, dan pengaduan dalam satu tempat.">
    <title>@yield('title', 'Informasi Aplikasi') — {{ config('app.name', 'Perumku') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @endif
    @livewireStyles
</head>
<body class="bg-[#F6F8F7] text-[#1E293B] antialiased">
    {{ $slot ?? '' }}
    @yield('content')
    @livewireScripts
</body>
</html>
