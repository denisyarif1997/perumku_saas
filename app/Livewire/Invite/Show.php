<?php

namespace App\Livewire\Invite;

use App\Models\ResidentInvitation;
use App\Services\ResidentInvitationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/** Halaman publik /invite/{token}: warga isi data + buat password. */
class Show extends Component
{
    public string $token = '';

    public ?ResidentInvitation $invitation = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $done = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = ResidentInvitation::findByToken($token);

        if ($this->invitation) {
            $this->invitation->loadMissing(['estate', 'house.block']);
            $this->name = $this->invitation->name;
            $this->email = $this->invitation->email ?? '';
            $this->phone = $this->invitation->phone ?? '';
        }
    }

    public function accept(ResidentInvitationService $service)
    {
        if (! $this->invitation) {
            $this->addError('token', 'Undangan tidak ditemukan / kedaluwarsa.');

            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        try {
            $user = $service->accept($this->invitation->fresh(), $data);
        } catch (RuntimeException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        Auth::login($user);
        session()->regenerate();

        $this->done = true;

        return $this->redirectRoute('resident.dashboard', navigate: true);
    }

    #[Layout('layouts.auth')]
    public function render()
    {
        return view('livewire.invite.show');
    }
}
