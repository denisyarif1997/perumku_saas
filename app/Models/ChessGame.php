<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEstate;
use App\Support\ChessEngine;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChessGame extends Model
{
    use BelongsToEstate;

    /**
     * Pertandingan hanya antar pemain dari estate yang sama, agar warga
     * tidak dapat diundang/di-pairing dengan warga estate lain.
     */
    public function applyEstateScope(Builder $query, int $estateId): void
    {
        if ($estateId === CurrentEstate::NONE) {
            $query->whereRaw('1 = 0');

            return;
        }

        $inEstate = fn (Builder $q) => $q
            ->where(fn (Builder $u) => $u->where('housing_estate_id', $estateId)->orWhereNull('housing_estate_id'));

        // Tantangan yang masih pending belum punya white/black (baru diisi saat
        // diterima), jadi hanya inviter — yang selalu ada — yang wajib cocok.
        $query->whereHas('inviter', $inEstate)
            ->where(function (Builder $q) use ($inEstate) {
                $q->whereNull('white_user_id')->orWhereHas('whitePlayer', $inEstate);
            })
            ->where(function (Builder $q) use ($inEstate) {
                $q->whereNull('black_user_id')->orWhereHas('blackPlayer', $inEstate);
            })
            ->where(function (Builder $q) use ($inEstate) {
                $q->whereNull('invitee_user_id')->orWhereHas('invitee', $inEstate);
            });
    }

    protected $fillable = [
        'white_user_id', 'black_user_id', 'inviter_user_id', 'invitee_user_id',
        'status', 'winner', 'end_reason', 'board', 'turn', 'castling',
        'en_passant', 'halfmove', 'fullmove', 'moves',
    ];

    protected function casts(): array
    {
        return [
            'board' => 'array',
            'moves' => 'array',
            'en_passant' => 'integer',
            'halfmove' => 'integer',
            'fullmove' => 'integer',
        ];
    }

    public function whitePlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_user_id');
    }

    public function blackPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_user_id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }

    /**
     * Warna (w/b) pemain pada permainan aktif, null bila bukan pemain.
     */
    public function colorOfUser(int $userId): ?string
    {
        if ($this->white_user_id === $userId) {
            return 'w';
        }
        if ($this->black_user_id === $userId) {
            return 'b';
        }

        return null;
    }

    public function isPlayer(int $userId): bool
    {
        return $this->colorOfUser($userId) !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Label alasan permainan berakhir.
     */
    public function endReasonLabel(): string
    {
        return match ($this->end_reason) {
            'checkmate' => 'Skakmat',
            'stalemate' => 'Pat (Stalemate)',
            'resign' => 'Menyerah',
            'fifty_move' => 'Remis 50 Langkah',
            'agreement' => 'Kesepakatan Remis',
            default => 'Selesai',
        };
    }

    /**
     * Buat tantangan baru dengan papan posisi awal.
     */
    public static function createChallenge(int $inviterId, ?int $inviteeId): self
    {
        return static::create([
            'inviter_user_id' => $inviterId,
            'invitee_user_id' => $inviteeId,
            'status' => 'pending',
            'board' => ChessEngine::initialBoard(),
            'turn' => 'w',
            'castling' => 'KQkq',
        ]);
    }

    /**
     * Terima tantangan: tetapkan warna (pemberi tantangan = putih).
     */
    public function accept(int $userId): void
    {
        $this->forceFill([
            'white_user_id' => $this->inviter_user_id,
            'black_user_id' => $userId === $this->inviter_user_id ? $this->invitee_user_id : $userId,
            'status' => 'active',
        ])->save();
    }

    public function decline(int $userId): void
    {
        $this->forceFill(['status' => 'declined'])->save();
    }

    public function cancel(): void
    {
        $this->forceFill(['status' => 'cancelled'])->save();
    }

    /**
     * Terapkan langkah dan update state permainan.
     *
     * @return array{captured: ?string}
     */
    public function playMove(int $from, int $to): array
    {
        $board = $this->board;
        $piece = $board[$from] ?? null;

        $result = ChessEngine::applyMove($board, $from, $to, $this->en_passant);

        $this->halfmove = ($result['captured'] !== null || ChessEngine::typeOf($piece) === 'P')
            ? 0
            : $this->halfmove + 1;

        $this->fullmove = $this->turn === 'b' ? $this->fullmove + 1 : $this->fullmove;
        $this->castling = ChessEngine::nextCastling($from, $to, $this->castling);
        $this->en_passant = ChessEngine::nextEnPassant($piece, $from, $to);
        $this->board = $result['board'];

        // Rekam histori langkah (dari kotak X ke kotak Y).
        $this->moves = array_merge($this->moves ?? [], [[
            'color' => $piece[0],
            'piece' => $piece[1],
            'from' => ChessEngine::squareName($from),
            'to' => ChessEngine::squareName($to),
            'capture' => $result['captured'] !== null,
        ]]);

        $this->turn = $this->turn === 'w' ? 'b' : 'w';

        $status = ChessEngine::gameStatus(
            $this->board, $this->turn, $this->castling, $this->en_passant, $this->halfmove,
        );

        if ($status['over']) {
            $this->status = 'finished';
            $this->winner = $status['winner'];
            $this->end_reason = $status['reason'];
        }

        $this->save();

        return ['captured' => $result['captured']];
    }

    public function resign(int $userId): void
    {
        $color = $this->colorOfUser($userId);

        $this->forceFill([
            'status' => 'finished',
            'winner' => $color === 'w' ? 'b' : 'w',
            'end_reason' => 'resign',
        ])->save();
    }
}
