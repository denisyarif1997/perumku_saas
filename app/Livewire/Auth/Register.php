<?php

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Component;

class Register extends Component
{
    public string $estateCode = '';

    public string $estateName = '';

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    /** Snake_case agar aturan validasi "confirmed" mencarinya dengan benar. */
    public string $password_confirmation = '';

    #[Layout('layouts.auth')]
    public function render()
    {
        return view('livewire.auth.register');
    }

    public function register()
    {
        $data = $this->validate([
            'estateCode' => ['required', 'string', 'max:20', 'alpha_dash'],
            'estateName' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'min:10', 'max:15'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'estateCode.required' => 'Kode perumahan wajib diisi.',
            'estateCode.alpha_dash' => 'Kode perumahan hanya boleh huruf, angka, tanda hubung, dan garis bawah.',
            'estateName.required' => 'Nama perumahan wajib diisi.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor HP/WA wajib diisi.',
            'phone.min' => 'Nomor HP/WA minimal 10 digit.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $targetWhatsapp = '6289525645332';

        $message = "Halo Admin, saya ingin mendaftarkan perumahan baru:\n\n"
            . "• Kode Perumahan: " . strtoupper($data['estateCode']) . "\n"
            . "• Nama Perumahan: {$data['estateName']}\n"
            . "• Nama Admin: {$data['name']}\n"
            . "• No HP/WA: {$data['phone']}\n"
            . "• Email: {$data['email']}\n"
            . "• Password Request: {$data['password']}\n\n"
            . "Mohon dapat diproses akun perumahan kami. Terima kasih!";

        $url = 'https://wa.me/' . $targetWhatsapp . '?text=' . rawurlencode($message);

        return redirect()->away($url);
    }

    public function sendToWhatsapp()
{
    $data = $this->validate([
        'estateCode' => ['required', 'string', 'max:20', 'alpha_dash'],
        'estateName' => ['required', 'string', 'max:100'],
        'name'       => ['required', 'string', 'max:100'],
        'phone'      => ['required', 'string', 'min:10', 'max:15'],
        'email'      => ['required', 'email', 'max:150'],
        'password'   => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $targetWhatsapp = '6289525645332';

    $message = "Halo Admin, saya ingin mendaftarkan perumahan baru di aplikasi perumku:\n\n"
        . "• Kode Perumahan: " . strtoupper($data['estateCode']) . "\n"
        . "• Nama Perumahan: {$data['estateName']}\n"
        . "• Nama Admin: {$data['name']}\n"
        . "• No HP/WA: {$data['phone']}\n"
        . "• Email: {$data['email']}\n"
        . "• Password Request: {$data['password']}\n\n"
        . "Mohon dapat diproses akun perumahan kami. Terima kasih!";

    return redirect()->away('https://wa.me/' . $targetWhatsapp . '?text=' . rawurlencode($message));
}
}