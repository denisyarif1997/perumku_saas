<?php

namespace App\Livewire\Resident\Chess;

use App\Models\ChessGame;
use App\Support\ChessEngine;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Play extends Component
{
    public ChessGame $game;

    /** Kotak yang dipilih (index 0-63) — untuk interaksi papan. */
    public ?int $selected = null;

    /** Tampilkan/sembunyikan panel histori (per pemain, toggle via tombol). */
    public bool $showHistory = true;

    /**
     * Posisi replay riwayat: null = live, selain itu = jumlah langkah yang
     * ditampilkan dari awal (0 = posisi awal, N = setelah langkah ke-N).
     */
    public ?int $replayAt = null;

    public function mount(ChessGame $game): void
    {
        // Pemain ATAU warga lain (penonton) boleh membuka permainan aktif;
        // penonton bersifat read-only (tidak bisa menggerakkan bidak).
        abort_unless(
            $game->isPlayer(auth()->id()) || $game->status !== 'pending',
            403
        );

        $this->game = $game;

        // Penonton langsung disuguhi panel histori agar bisa mengikuti jalannya permainan.
        if (! $game->isPlayer(auth()->id())) {
            $this->showHistory = true;
        }
    }

    /**
     * Klik kotak: pilih bidak sendiri, atau jalankan langkah bila sudah ada kotak terpilih.
     */
    public function tapSquare(int $index): void
    {
        // Mode replay riwayat: papan terkunci agar tidak bingung dengan posisi live.
        if ($this->replayAt !== null) {
            $this->dispatch('notify', type: 'error', message: 'Mode lihat histori — tekan "Kembali Live" untuk bermain.');

            return;
        }

        $userId = auth()->id();
        abort_unless($this->game->isActive() && $this->game->isPlayer($userId), 403);

        $color = $this->game->colorOfUser($userId);

        if ($this->game->turn !== $color) {
            $this->dispatch('notify', type: 'error', message: 'Sekarang giliran lawan.');

            return;
        }

        $board = $this->game->board;

        // Belum ada kotak terpilih → pilih bidak sendiri.
        if ($this->selected === null) {
            $piece = $board[$index] ?? null;
            if ($piece !== null && ChessEngine::colorOf($piece) === $color) {
                $this->selected = $index;
            }

            return;
        }

        // Klik kotak asal lagi → batalkan pilihan.
        if ($this->selected === $index) {
            $this->selected = null;

            return;
        }

        // Klik bidak sendiri lain → ganti pilihan.
        $piece = $board[$index] ?? null;
        if ($piece !== null && ChessEngine::colorOf($piece) === $color) {
            $this->selected = $index;

            return;
        }

        // Validasi langkah di sisi server.
        $legal = ChessEngine::pieceMoves(
            $board, $this->selected, $board[$this->selected], $this->game->castling, $this->game->en_passant,
        );

        if (! in_array($index, $legal, true)) {
            $this->dispatch('notify', type: 'error', message: 'Langkah tidak valid.');
            $this->selected = null;

            return;
        }

        $from = $this->selected;
        $sim = ChessEngine::applyMove($board, $from, $index, $this->game->en_passant);

        if (ChessEngine::inCheck($sim['board'], $color)) {
            $this->dispatch('notify', type: 'error', message: 'Raja Anda akan skak. Langkah dibatalkan.');
            $this->selected = null;

            return;
        }

        $this->game->playMove($from, $index);
        $this->selected = null;
    }

    public function resign(): void
    {
        abort_unless($this->game->isActive() && $this->game->isPlayer(auth()->id()), 403);

        $this->game->resign(auth()->id());
    }

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
    }

    /**
     * Kontrol replay riwayat histori.
     */
    public function replayStart(): void
    {
        $this->showHistory = true;
        $this->replayAt = 0;
        $this->selected = null;
    }

    public function replayPrev(): void
    {
        if ($this->replayAt === null) {
            return;
        }

        $this->replayAt = max(0, $this->replayAt - 1);
    }

    public function replayNext(): void
    {
        $total = count($this->game->moves ?? []);

        if ($this->replayAt === null) {
            return;
        }

        $this->replayAt = min($total, $this->replayAt + 1);

        if ($this->replayAt >= $total) {
            $this->replayAt = null;
        }
    }

    public function replayJump(int $step): void
    {
        $total = count($this->game->moves ?? []);
        $step = max(0, min($total, $step));

        $this->showHistory = true;
        $this->replayAt = $step >= $total ? null : $step;
        $this->selected = null;
    }

    public function replayLive(): void
    {
        $this->replayAt = null;
        $this->selected = null;
    }

    /**
     * Daftar langkah legal dari kotak terpilih (untuk highlight UI).
     *
     * @return array<int, int>
     */
    protected function legalTargets(): array
    {
        if ($this->selected === null) {
            return [];
        }

        $board = $this->game->board;
        $piece = $board[$this->selected] ?? null;
        if ($piece === null) {
            return [];
        }

        $legal = ChessEngine::pieceMoves($board, $this->selected, $piece, $this->game->castling, $this->game->en_passant);
        $color = ChessEngine::colorOf($piece);

        return array_values(array_filter($legal, function ($to) use ($board, $color) {
            $sim = ChessEngine::applyMove($board, $this->selected, $to, $this->game->en_passant);

            return ! ChessEngine::inCheck($sim['board'], $color);
        }));
    }

    #[Layout('layouts.resident', ['title' => 'Main Catur'])]
    public function render()
    {
        $moves = $this->game->moves ?? [];
        $board = $this->game->board;

        // Mode replay: rekonstruksi papan dari N langkah pertama riwayat.
        $replayAt = $this->replayAt;
        if ($replayAt !== null) {
            $replayAt = max(0, min(count($moves), $replayAt));
            $board = ChessEngine::initialBoard();
            for ($i = 0; $i < $replayAt; $i++) {
                $from = ChessEngine::squareIndex($moves[$i]['from']);
                $to = ChessEngine::squareIndex($moves[$i]['to']);
                $board = ChessEngine::applyMove($board, $from, $to, null)['board'];
            }
        }

        $targets = $this->replayAt !== null ? [] : $this->legalTargets();

        return view('livewire.resident.chess.play', [
            'board' => $board,
            'targets' => $targets,
            'myColor' => $this->game->colorOfUser(auth()->id()),
            'inCheck' => $this->game->isActive() ? ChessEngine::inCheck($board, $this->game->turn) : false,
            'moves' => $moves,
            'replayAt' => $replayAt,
        ]);
    }
}
