<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentProofController extends Controller
{
    /**
     * Stream bukti bayar yang disimpan sebagai BLOB (maks 2 MB).
     * Fallback ke file storage lama bila data blob belum ada.
     */
    public function show(Request $request, Payment $payment): Response
    {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless(
            $user->can('view', $payment),
            403,
            'Anda tidak berhak melihat bukti pembayaran ini.'
        );

        // Ambil ulang agar kolom blob pasti terbaca segar dari DB.
        $payment->refresh();

        $blob = $payment->proof_blob;

        if (is_resource($blob)) {
            $blob = stream_get_contents($blob);
        }

        if (! empty($blob)) {
            $mime = $payment->proof_mime ?: 'image/jpeg';
            $name = $payment->proof_name ?: ('bukti-'.$payment->payment_number.'.jpg');

            // Keamanan: hanya izinkan gambar.
            if (! str_starts_with($mime, 'image/')) {
                abort(415, 'Bukti pembayaran bukan gambar.');
            }

            return response($blob, 200, [
                'Content-Type' => $mime,
                'Content-Length' => strlen($blob),
                'Content-Disposition' => 'inline; filename="'.$name.'"',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        // Kompatibilitas data lama (path di storage/public).
        if ($payment->proof && Storage::disk('public')->exists($payment->proof)) {
            $path = Storage::disk('public')->path($payment->proof);
            $mime = Storage::disk('public')->mimeType($payment->proof) ?: 'image/jpeg';

            return response()->file($path, ['Content-Type' => $mime]);
        }

        abort(404, 'Bukti pembayaran tidak ditemukan.');
    }
}
