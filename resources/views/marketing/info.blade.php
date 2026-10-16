@extends('layouts.marketing')

@section('title', 'Solusi Manajemen Perumahan')
@section('content')
    <header class="sticky top-0 z-30 border-b border-[#EEF2F1] bg-white/85 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-teal-700 text-white shadow-lg shadow-teal-600/30">
                    <i data-lucide="home" class="h-5 w-5"></i>
                </div>
                <p class="text-[17px] font-bold text-[#134E4A]">{{ config('app.name', 'Perumku') }}</p>
            </div>
            <nav class="flex items-center gap-2">
                <a href="{{ route('login') }}"
                    class="min-h-[40px] rounded-xl px-4 py-2 text-[14px] font-semibold text-[#334155] transition hover:bg-[#F1F5F9]">
                    Masuk
                </a>
                <a href="{{ route('register') }}"
                    class="min-h-[40px] rounded-xl bg-gradient-to-br from-teal-600 to-teal-700 px-4 py-2 text-[14px] font-semibold text-white shadow-lg shadow-teal-700/30 transition hover:from-teal-500 hover:to-teal-600">
                    Daftar
                </a>
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-400/20 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-24 h-80 w-80 rounded-full bg-emerald-400/15 blur-3xl"></div>

            <div class="relative mx-auto max-w-6xl px-5 py-16 md:py-24">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3.5 py-1.5 text-[12px] font-semibold text-teal-700">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                        Aplikasi untuk pengurus perumahan
                    </span>
                    <h1 class="mt-6 font-[family-name:var(--font-jakarta)] text-4xl font-extrabold leading-[1.1] tracking-tight text-[#0F172A] md:text-5xl">
                        Urus perumahan Anda<br class="hidden md:block">
                        cukup dari <span class="text-teal-600">satu aplikasi</span>
                    </h1>
                    <p class="mx-auto mt-5 max-w-2xl text-[17px] leading-relaxed text-[#475569]">
                        Iuran warga, kas, data warga, pengumuman, forum, sampai laporan aduan warga - semuanya rapi
                        di satu tempat. Gratis dicoba 15 hari, tanpa kartu kredit.
                    </p>
                    <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <a href="{{ route('register') }}"
                            class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-br from-teal-600 to-teal-700 px-8 text-[15px] font-semibold text-white shadow-lg shadow-teal-700/30 transition hover:from-teal-500 hover:to-teal-600 sm:w-auto">
                            <i data-lucide="rocket" class="h-4 w-4"></i> Daftar  Sekarang
                        </a>
                        {{-- <a href="https://wa.me/6289525645332" target="_blank" rel="noopener noreferrer"
                            class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-2xl border border-[#E2E8F0] bg-white px-8 text-[15px] font-semibold text-[#334155] shadow-sm transition hover:bg-[#F8FAFC] sm:w-auto">
                            <i data-lucide="message-circle" class="h-4 w-4 text-emerald-600"></i> Tanya Dulu Lewat WhatsApp
                        </a> --}}
                    </div>
                    {{-- <p class="mt-4 text-[13px] text-[#94A3B8]">Cukup hubungi kami via WhatsApp.</p> --}}
                </div>
            </div>
        </section>

        {{-- Masalah yang biasa dialami --}}
        <section class="border-y border-[#EEF2F1] bg-white">
            <div class="mx-auto max-w-6xl px-5 py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                        Ini yang biasa dikeluhkan pengurus perumahan 
                    </h2>
                    <p class="mt-3 text-[16px] text-[#64748B]">
                        Catatan iuran di buku, data warga di WhatsApp, uang kas cuma diingat-ingat sendiri.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['icon' => 'file-spreadsheet', 'title' => 'Data berantakan di mana-mana', 'text' => 'Data warga, rumah, dan tagihan tersebar di Excel, buku catatan, dan grup WhatsApp. Susah tahu angka yang benar.'],
                        ['icon' => 'clock-alert', 'title' => 'Tagihan sering telat dibuat', 'text' => 'Bikin tagihan masih satu-satu secara manual, dan tidak ada pengingat otomatis untuk warga yang belum bayar.'],
                        ['icon' => 'banknote', 'title' => 'Uang kas susah dipertanggungjawabkan', 'text' => 'Tidak ada catatan uang masuk dan keluar yang rapi, jadi saldo kas sering tidak jelas.'],
                        ['icon' => 'users', 'title' => 'Laporan warga numpuk di chat pribadi', 'text' => 'Laporan warga sering kelupaan, tidak tertangani, dan tidak ada catatan sampai mana prosesnya.'],
                        ['icon' => 'file-text', 'title' => 'Bikin laporan makan waktu lama', 'text' => 'Laporan tunggakan, setoran, dan kwitansi masih dibuat manual tiap akhir bulan.'],
                        // ['icon' => 'gauge', 'title' => 'Catat meteran air masih manual', 'text' => 'Angka meteran dicatat di buku atau difoto, lalu disalin ulang lagi ke aplikasi pembayaran.'],
                    ] as $pain)
                        <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-6">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                                <i data-lucide="{{ $pain['icon'] }}" class="h-5 w-5"></i>
                            </span>
                            <h3 class="mt-4 text-[16px] font-bold text-[#0F172A]">{{ $pain['title'] }}</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-[#64748B]">{{ $pain['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Fitur --}}
        <section>
            <div class="mx-auto max-w-6xl px-5 py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                        Semua kebutuhan sudah ada di dalamnya
                    </h2>
                    <p class="mt-3 text-[16px] text-[#64748B]">
                        Delapan bagian utama yang saling terhubung, jadi tidak perlu pindah-pindah aplikasi.
                    </p>
                </div>

                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['icon' => 'building-2', 'title' => 'Data Warga & Rumah', 'text' => 'Data perumahan, blok, rumah, dan warga, lengkap dengan riwayatnya.'],
                        ['icon' => 'receipt', 'title' => 'Iuran & Tagihan', 'text' => 'Tarif iuran diatur sekali, lalu tagihan bulanan langsung dibuat otomatis dan dikirim ke warga.'],
                        ['icon' => 'wallet', 'title' => 'Cek Bukti Bayar', 'text' => 'Warga unggah bukti transfer, pengurus tinggal cek dan setujui. Kas langsung tercatat otomatis.'],
                        ['icon' => 'package', 'title' => 'Peminjaman Barang', 'text' => 'Barang inventaris perumahan bisa dipinjam warga, status kembalinya tercatat rapi.'],
                        ['icon' => 'coins', 'title' => 'Kas Warga', 'text' => 'Semua uang masuk dan keluar tercatat rapi, mudah dicek kapan saja.'],
                        ['icon' => 'clipboard-list', 'title' => 'Laporan Warga', 'text' => 'Warga lapor, pengurus balas, dan statusnya bisa dipantau sampai selesai.'],
                        ['icon' => 'megaphone', 'title' => 'Pengumuman', 'text' => 'Sampaikan info penting ke semua warga sekaligus, tidak perlu broadcast manual.'],
                        ['icon' => 'messages-square', 'title' => 'Forum Warga', 'text' => 'Tempat ngobrol dan diskusi antarwarga dalam satu perumahan.'],
                    ] as $feature)
                        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm transition hover:shadow-md">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                                <i data-lucide="{{ $feature['icon'] }}" class="h-5 w-5"></i>
                            </span>
                            <h3 class="mt-4 text-[15px] font-bold text-[#0F172A]">{{ $feature['title'] }}</h3>
                            <p class="mt-1.5 text-[13px] leading-relaxed text-[#64748B]">{{ $feature['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Contoh tampilan warga --}}
        <section class="border-y border-[#EEF2F1] bg-white">
            <div class="mx-auto max-w-6xl px-5 py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                        Lihat tampilan di sisi warga
                    </h2>
                    <p class="mt-3 text-[16px] text-[#64748B]">
                        Contoh tampilan yang dilihat warga lewat HP-nya. Semua data di bawah ini hanya ilustrasi.
                    </p>
                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <div>
                        <div class="mx-auto w-full max-w-[290px] overflow-hidden rounded-[28px] border-[6px] border-[#0F172A] bg-[#F6F8F7] shadow-xl">
                            <div class="flex items-center justify-between bg-[#F6F8F7]/80 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white">
                                        <i data-lucide="home" class="h-4 w-4"></i>
                                    </div>
                                    <p class="text-[13px] font-bold text-[#134E4A]">Perumku</p>
                                </div>
                                <i data-lucide="bell" class="h-4 w-4 text-[#64748B]"></i>
                            </div>
                            <div class="space-y-3 px-3 pb-4">
                                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-600 to-teal-800 p-4 text-white">
                                    <p class="text-[11px] text-teal-100">Selamat datang</p>
                                    <p class="text-[17px] font-bold">Budi</p>
                                    <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold">
                                        <i data-lucide="house" class="h-3 w-3"></i> Blok A-12
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-white p-3.5 shadow-sm">
                                    <p class="text-[13px] font-bold text-[#134E4A]">Iuran</p>
                                    <div class="mt-2 rounded-xl bg-emerald-50 p-2.5 text-[12px] font-semibold text-emerald-700">
                                        Semua iuran sudah lunas
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-center text-[10px] font-medium text-[#475569]">
                                    <div class="rounded-xl bg-white p-2 shadow-sm"><i data-lucide="wallet" class="mx-auto h-5 w-5 text-teal-600"></i>Iuran</div>
                                    <div class="rounded-xl bg-white p-2 shadow-sm"><i data-lucide="messages-square" class="mx-auto h-5 w-5 text-sky-500"></i>Forum</div>
                                    <div class="rounded-xl bg-white p-2 shadow-sm"><i data-lucide="wrench" class="mx-auto h-5 w-5 text-violet-500"></i>Aduan</div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-center text-[14px] font-bold text-[#0F172A]">Beranda warga</p>
                        <p class="text-center text-[13px] text-[#64748B]">Ringkasan iuran dan menu cepat.</p>
                    </div>
                    <div>
                        <div class="mx-auto w-full max-w-[290px] overflow-hidden rounded-[28px] border-[6px] border-[#0F172A] bg-[#F6F8F7] shadow-xl">
                            <div class="flex items-center justify-between bg-[#F6F8F7]/80 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white">
                                        <i data-lucide="home" class="h-4 w-4"></i>
                                    </div>
                                    <p class="text-[13px] font-bold text-[#134E4A]">Perumku</p>
                                </div>
                                <i data-lucide="bell" class="h-4 w-4 text-[#64748B]"></i>
                            </div>
                            <div class="space-y-2.5 px-3 pb-4">
                                <p class="px-1 text-[14px] font-bold text-[#134E4A]">Tagihan Iuran</p>
                                <div class="rounded-2xl bg-red-50 p-3">
                                    <p class="text-[12px] font-bold text-red-700">1 iuran lewat jatuh tempo</p>
                                    <div class="mt-2 rounded-xl bg-white p-2.5 shadow-sm">
                                        <p class="text-[13px] font-bold text-slate-900">Iuran September 2026</p>
                                        <p class="text-[11px] text-slate-500">Rumah Blok A-12 · JT 10/10/2026</p>
                                        <p class="mt-1 text-[13px] font-bold text-red-700">Rp 150.000</p>
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-white p-2.5 shadow-sm">
                                    <p class="text-[13px] font-bold">Iuran Oktober 2026</p>
                                    <p class="text-[11px] text-slate-500">Rumah Blok A-12 · JT 10/11/2026</p>
                                    <div class="mt-1 flex items-center justify-between">
                                        <p class="text-[13px] font-bold">Rp 150.000</p>
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800">Belum bayar</span>
                                    </div>
                                </div>
                                <div class="rounded-xl bg-teal-600 py-2.5 text-center text-[13px] font-semibold text-white">Unggah Bukti Bayar</div>
                            </div>
                        </div>
                        <p class="mt-4 text-center text-[14px] font-bold text-[#0F172A]">Tagihan &amp; bukti bayar</p>
                        <p class="text-center text-[13px] text-[#64748B]">Warga lihat tagihan dan unggah bukti transfer.</p>
                    </div>
                        <div class="mx-auto w-full max-w-[290px] overflow-hidden rounded-[28px] border-[6px] border-[#0F172A] bg-[#F6F8F7] shadow-xl">
                            <div class="flex items-center justify-between bg-[#F6F8F7]/80 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white">
                                        <i data-lucide="home" class="h-4 w-4"></i>
                                    </div>
                                    <p class="text-[13px] font-bold text-[#134E4A]">Perumku</p>
                                </div>
                                <i data-lucide="bell" class="h-4 w-4 text-[#64748B]"></i>
                            </div>
                            <div class="space-y-2.5 px-3 pb-4">
                                <p class="px-1 text-[14px] font-bold text-[#134E4A]">Laporan Aduan</p>
                                <div class="rounded-2xl bg-white p-2.5 shadow-sm">
                                    <p class="text-[13px] font-bold">Lampu jalan mati</p>
                                    <p class="text-[11px] text-slate-500">Dibalas pengurus · sedang ditindaklanjuti</p>
                                    <span class="mt-1 inline-block rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-semibold text-sky-800">Diproses</span>
                                </div>
                                <div class="rounded-2xl bg-white p-2.5 shadow-sm">
                                    <p class="text-[13px] font-bold">Sampah menumpuk</p>
                                    <p class="text-[11px] text-slate-500">Masalah sudah diselesaikan pengurus</p>
                                    <span class="mt-1 inline-block rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800">Selesai</span>
                                </div>
                                <div class="rounded-2xl bg-white p-2.5 shadow-sm">
                                    <p class="text-[13px] font-bold">Forum Warga</p>
                                    <p class="text-[11px] text-slate-500">Diskusi dan polling antarwarga satu perumahan</p>
                                </div>
                                <div class="flex gap-1 rounded-2xl bg-white px-2 py-1.5 shadow-sm">
                                    @foreach (['home' => 'Home', 'wallet' => 'Iuran', 'messages-square' => 'Forum', 'wrench' => 'Aduan', 'user' => 'Profil'] as $icon => $label)
                                        <span class="flex flex-1 flex-col items-center gap-0.5 py-1 text-[9px] font-medium text-[#94A3B8]">
                                            <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>{{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-center text-[14px] font-bold text-[#0F172A]">Aduan &amp; menu bawah</p>
                        <p class="text-center text-[13px] text-[#64748B]">Lapor masalah dan pantau statusnya sampai selesai.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Peran pengguna --}}
        <section>
            <div class="mx-auto max-w-6xl px-5 py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                        Setiap orang lihat bagiannya sendiri
                    </h2>
                    <p class="mt-3 text-[16px] text-[#64748B]">
                        Delapan jenis akun bawaan, hak aksesnya bisa diatur per fitur sesuai kebutuhan perumahan.
                    </p>
                </div>

                <div class="mt-10 flex flex-wrap justify-center gap-2.5">
                    @foreach ([
                        'Admin', 'Bendahara', 'RT', 'RW', 'Warga',
                    ] as $role)
                        <span class="rounded-full border border-[#E2E8F0] bg-white px-4 py-2 text-[14px] font-semibold text-[#334155] shadow-sm">
                            {{ $role }}
                        </span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Cara kerja --}}
        <section class="border-y border-[#EEF2F1] bg-white">
            <div class="mx-auto max-w-6xl px-5 py-16">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                        Mulai hanya dengan tiga langkah
                    </h2>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    @foreach ([
                        ['step' => '01', 'title' => 'Daftarkan perumahan Anda', 'text' => 'Isi form pendaftaran, lalu tim kami akan menghubungi Anda untuk mengaktifkan akun.'],
                        ['step' => '02', 'title' => 'Masukkan data awal', 'text' => 'Data blok, rumah, warga, dan tarif iuran. Data lama boleh dimasukkan pelan-pelan, tidak harus sekaligus.'],
                        ['step' => '03', 'title' => 'Ajak warga bergabung', 'text' => 'Setiap warga punya akun sendiri dan langsung bisa lihat tagihannya masing-masing.'],
                    ] as $step)
                        <div class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-6">
                            <span class="font-[family-name:var(--font-jakarta)] text-3xl font-extrabold text-teal-600/30">{{ $step['step'] }}</span>
                            <h3 class="mt-2 text-[16px] font-bold text-[#0F172A]">{{ $step['title'] }}</h3>
                            <p class="mt-2 text-[14px] leading-relaxed text-[#64748B]">{{ $step['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section>
            <div class="mx-auto max-w-3xl px-5 py-16">
                <h2 class="text-center font-[family-name:var(--font-jakarta)] text-3xl font-bold text-[#0F172A]">
                    Pertanyaan yang sering ditanyakan
                </h2>

                <div class="mt-8 space-y-3">
                    @foreach ([
                        ['q' => 'Apakah warga harus download aplikasi?', 'a' => 'Tidak perlu. Cukup dibuka lewat browser HP atau komputer, tidak perlu install apa pun.'],
                        ['q' => 'Berapa biayanya?', 'a' => 'Flat Rp50.000 per bulan per perumahan, sudah termasuk 100 akun warga. Kelebihan pengguna dikenai Rp1.000 per akun per bulan.'],
                        ['q' => 'Apakah ada masa percobaan gratis?', 'a' => 'Ada. Setiap perumahan baru langsung mendapat masa trial 15 hari dengan kuota 100 akun pengguna.'],
                        ['q' => 'Data saya masih di Excel, gimana caranya?', 'a' => 'Tidak masalah. Data bisa dimasukkan pelan-pelan, mulai dari data warga dan rumah, lalu tagihan berikutnya sudah bisa langsung dibuat di sistem.'],
                        ['q' => 'Bagaimana cara mulai memakai?', 'a' => 'Daftar dengan email, lengkapi data perumahan, lalu akun Anda diverifikasi oleh tim kami sebelum perumahan bisa digunakan.'],
                    ] as $faq)
                        <details class="group rounded-2xl border border-[#E2E8F0] bg-white px-5 py-4 shadow-sm">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[15px] font-semibold text-[#0F172A]">
                                {{ $faq['q'] }}
                                <i data-lucide="chevron-down" class="h-4 w-4 shrink-0 text-[#94A3B8] transition group-open:rotate-180"></i>
                            </summary>
                            <p class="mt-3 text-[14px] leading-relaxed text-[#64748B]">{{ $faq['a'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- CTA penutup --}}
        <section class="px-5 pb-20">
            <div class="mx-auto max-w-5xl overflow-hidden rounded-[28px] bg-gradient-to-br from-[#134E4A] to-teal-700 px-6 py-14 text-center shadow-xl shadow-teal-800/20 md:px-14">
                <h2 class="font-[family-name:var(--font-jakarta)] text-3xl font-bold text-white md:text-4xl">
                    Siap coba sekarang?
                </h2>
                <p class="mx-auto mt-4 max-w-xl text-[16px] text-teal-100">
                    Daftar sekarang, tim kami akan menghubungi Anda. Gratis, tidak perlu kartu kredit.
                </p>
                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('register') }}"
                        class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-white px-8 text-[15px] font-semibold text-[#134E4A] shadow-lg transition hover:bg-teal-50 sm:w-auto">
                        <i data-lucide="rocket" class="h-4 w-4"></i> Daftar Sekarang
                    </a>
                    <a href="https://wa.me/62881012463071" target="_blank" rel="noopener noreferrer"
                        class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-2xl border border-white/30 bg-white/10 px-8 text-[15px] font-semibold text-white transition hover:bg-white/20 sm:w-auto">
                        <i data-lucide="message-circle" class="h-4 w-4"></i> Tanya Dulu ke Kami
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-[#EEF2F1] bg-white">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-5 py-8 text-center sm:flex-row sm:text-left">
            <div class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-teal-700 text-white">
                    <i data-lucide="home" class="h-4 w-4"></i>
                </div>
                <p class="text-[15px] font-bold text-[#134E4A]">{{ config('app.name', 'Perumku') }}</p>
            </div>
            <p class="text-[13px] text-[#94A3B8]">
                &copy; {{ date('Y') }} {{ config('app.name', 'Perumku') }}. Aplikasi manajemen perumahan.
            </p>
        </div>
    </footer>
@endsection