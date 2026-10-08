<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\CurrentEstate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat kunjungan halaman untuk statistik pemakaian aplikasi.
 *
 * Yang dilewati:
 * - metode selain GET, sehingga update komponen Livewire dan POST form
 *   tidak pernah terhitung;
 * - endpoint teknis Livewire, health check, dan berkas aset;
 * - respons non-HTML (gambar, unduhan) serta halaman gagal (4xx/5xx);
 * - kunjungan berulang ke halaman yang sama dalam jendela dedupe
 *   (lihat PageView::isDuplicateOfRecentVisit).
 *
 * Navigasi wire:navigate tetap dihitung: permintaannya memang fetch, tetapi
 * setiap perpindahan halaman menghasilkan tepat satu permintaan, sehingga
 * jumlah kunjungan tetap merepresentasikan perpindahan halaman nyata.
 */
class TrackPageVisit
{
    /** Path teknis yang tidak pernah dihitung sebagai kunjungan. */
    protected const IGNORED_PREFIXES = ['livewire', 'up', 'build', 'storage', 'vendor', 'fonts'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            $this->record($request);
        }

        return $response;
    }

    /**
     * Apakah respons ini layak dihitung sebagai kunjungan pengguna.
     */
    protected function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Hanya halaman HTML: aset, gambar, dan unduhan tidak dihitung.
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // Halaman yang gagal tidak merepresentasikan pemakaian aplikasi.
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        return ! $this->isTechnicalPath($request->path());
    }

    /**
     * True untuk path bertingkat milik framework atau aset, seperti
     * "livewire/update", "up", atau "build/assets/app.js".
     */
    protected function isTechnicalPath(string $path): bool
    {
        $segments = explode('/', ltrim($path, '/'));

        return in_array($segments[0], self::IGNORED_PREFIXES, true);
    }

    /**
     * Simpan satu kunjungan.
     */
    protected function record(Request $request): void
    {
        $user = $request->user();
        $path = '/'.ltrim($request->path(), '/');
        $ip = $request->ip();

        if (PageView::isDuplicateOfRecentVisit($path, $user?->id, $ip)) {
            return;
        }

        PageView::create([
            'user_id' => $user?->id,
            'housing_estate_id' => $this->estateIdFor(),
            'path' => $path,
            'ip_address' => $ip,
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Condominium asal pengunjung: staf dari akunnya, warga dari rumah
     * huniannya. Akun platform dan tamu disimpan null karena tidak punya
     * condominian.
     */
    protected function estateIdFor(): ?int
    {
        $estateId = CurrentEstate::id();

        return $estateId !== null && $estateId > 0 ? $estateId : null;
    }
}
