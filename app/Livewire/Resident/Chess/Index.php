<?php

namespace App\Livewire\Resident\Chess;

use App\Models\ChessGame;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    /** Tantangan yang dibuat user (menunggu lawan). */
    public ?int $myPendingId = null;

    public function mount(): void
    {
        $this->myPendingId = ChessGame::query()
            ->where('status', 'pending')
            ->where('inviter_user_id', auth()->id())
            ->latest()
            ->value('id');
    }

    /**
     * Buat tantangan terbuka — warga lain bisa melihat & menerimanya.
     */
    public function createOpenChallenge(): void
    {
        $this->abortUnfinished();

        ChessGame::createChallenge(auth()->id(), null);

        session()->flash('success', 'Tantangan dibuat. Menunggu warga lain menerima...');
    }

    /**
     * Invite warga tertentu (hanya warga dengan akun aktif).
     */
    public function invite(int $userId): void
    {
        $this->abortUnfinished();

        if ($userId === auth()->id()) {
            $this->dispatch('notify', type: 'error', message: 'Tidak bisa mengundang diri sendiri.');

            return;
        }

        // Satu tantangan aktif per pemain.
        ChessGame::where('status', 'pending')
            ->where(function ($q) {
                $q->where('inviter_user_id', auth()->id())->orWhere('invitee_user_id', auth()->id());
            })
            ->delete();

        ChessGame::createChallenge(auth()->id(), $userId);

        session()->flash('success', 'Undangan catur terkirim.');
    }

    /**
     * Terima tantangan (terbuka maupun personal).
     */
    public function accept(int $gameId)
    {
        $game = ChessGame::where('status', 'pending')->findOrFail($gameId);

        if ($game->invitee_user_id !== null && $game->invitee_user_id !== auth()->id()) {
            return;
        }

        if ($game->inviter_user_id === auth()->id()) {
            return; // tidak bisa menerima tantangan sendiri
        }

        $game->accept(auth()->id());

        return $this->redirectRoute('resident.chess.play', $game, navigate: true);
    }

    public function decline(int $gameId): void
    {
        ChessGame::where('status', 'pending')
            ->where('invitee_user_id', auth()->id())
            ->findOrFail($gameId)
            ->decline(auth()->id());
    }

    public function cancelPending(): void
    {
        ChessGame::where('status', 'pending')
            ->where('inviter_user_id', auth()->id())
            ->delete();

        $this->myPendingId = null;
    }

    #[On('notify')]
    public function refreshList(): void
    {
        //
    }

    private function abortUnfinished(): void
    {
        // Tandai permainan aktif milik user sebagai selesai (menyerah) bila masih ada.
        $active = ChessGame::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('white_user_id', auth()->id())->orWhere('black_user_id', auth()->id());
            })
            ->get();

        foreach ($active as $game) {
            $game->resign(auth()->id());
        }
    }

    #[Layout('layouts.resident', ['title' => 'Catur Warga'])]
    public function render()
    {
        $userId = auth()->id();

        return view('livewire.resident.chess.index', [
            // Undangan personal untuk saya.
            'invites' => ChessGame::query()
                ->with('inviter')
                ->where('status', 'pending')
                ->where('invitee_user_id', $userId)
                ->latest()
                ->get(),

            // Tantangan terbuka dari warga lain.
            'openChallenges' => ChessGame::query()
                ->with('inviter')
                ->where('status', 'pending')
                ->whereNull('invitee_user_id')
                ->where('inviter_user_id', '!=', $userId)
                ->latest()
                ->get(),

            // Permainan saya yang sedang berlangsung.
            'myGames' => ChessGame::query()
                ->with(['whitePlayer', 'blackPlayer'])
                ->where('status', 'active')
                ->where(function ($q) use ($userId) {
                    $q->where('white_user_id', $userId)->orWhere('black_user_id', $userId);
                })
                ->latest('updated_at')
                ->get(),

            // Permainan warga lain (publik — bisa ditonton & dilihat historinya).
            'publicGames' => ChessGame::query()
                ->with(['whitePlayer', 'blackPlayer'])
                ->whereIn('status', ['active', 'finished'])
                ->where(function ($q) use ($userId) {
                    $q->where('white_user_id', '!=', $userId)->orWhereNull('white_user_id');
                })
                ->where(function ($q) use ($userId) {
                    $q->where('black_user_id', '!=', $userId)->orWhereNull('black_user_id');
                })
                ->latest('updated_at')
                ->limit(10)
                ->get(),

            // Riwayat.
            'history' => ChessGame::query()
                ->with(['whitePlayer', 'blackPlayer'])
                ->where('status', 'finished')
                ->where(function ($q) use ($userId) {
                    $q->where('white_user_id', $userId)->orWhere('black_user_id', $userId);
                })
                ->latest('updated_at')
                ->limit(10)
                ->get(),

            // Warga lain yang bisa diundang (punya akun aktif).
            'residents' => User::query()
                ->where('id', '!=', $userId)
                ->whereNotNull('resident_id')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
