<?php

namespace App\Support;

/**
 * Engine catur lengkap tanpa dependensi eksternal.
 *
 * Representasi papan: array 64 elemen (index 0 = a8, index 63 = h1).
 * Nilai kotak: null (kosong) atau string dua karakter, contoh 'wK', 'bP'.
 */
class ChessEngine
{
    /**
     * Papan posisi awal.
     *
     * @return array<int, ?string>
     */
    public static function initialBoard(): array
    {
        $board = array_fill(0, 64, null);
        $backRank = ['R', 'N', 'B', 'Q', 'K', 'B', 'N', 'R'];

        for ($file = 0; $file < 8; $file++) {
            $board[$file] = 'b'.$backRank[$file];
            $board[8 + $file] = 'bP';
            $board[48 + $file] = 'wP';
            $board[56 + $file] = 'w'.$backRank[$file];
        }

        return $board;
    }

    /**
     * Konversi index 0-63 ke notasi algebrais, contoh 0 -> 'a8'.
     */
    public static function squareName(int $index): string
    {
        return chr(97 + ($index % 8)).strval(8 - intdiv($index, 8));
    }

    /**
     * Konversi notasi algebrais ('e4') ke index 0-63.
     */
    public static function squareIndex(string $name): int
    {
        $file = ord(strtolower($name[0])) - 97;
        $rank = (int) $name[1];

        return (8 - $rank) * 8 + $file;
    }

    public static function onBoard(int $index): bool
    {
        return $index >= 0 && $index < 64;
    }

    /**
     * Warna bidak: 'w' | 'b' | null.
     */
    public static function colorOf(?string $piece): ?string
    {
        return $piece ? $piece[0] : null;
    }

    /**
     * Jenis bidak: 'K','Q','R','B','N','P' | null.
     */
    public static function typeOf(?string $piece): ?string
    {
        return $piece ? $piece[1] : null;
    }

    /**
     * Daftar langkah pseudo-legal semua bidak satu warna.
     *
     * @param  array<int, ?string>  $board
     * @return array<int, array<int, int>> [from => [to, ...]]
     */
    public static function allMoves(array $board, string $color, string $castling, ?int $enPassant): array
    {
        $moves = [];

        foreach ($board as $from => $piece) {
            if ($piece === null || self::colorOf($piece) !== $color) {
                continue;
            }

            $moves[$from] = self::pieceMoves($board, (int) $from, $piece, $castling, $enPassant);
        }

        return $moves;
    }

    /**
     * Langkah pseudo-legal satu bidak (termasuk rokade, en passant).
     *
     * @param  array<int, ?string>  $board
     * @return array<int, int>
     */
    public static function pieceMoves(array $board, int $from, string $piece, string $castling, ?int $enPassant): array
    {
        $color = $piece[0];
        $type = $piece[1];
        $moves = [];

        $push = function (int $to) use ($board, $color, &$moves): bool {
            if (! self::onBoard($to)) {
                return false;
            }
            $target = $board[$to];
            if ($target === null) {
                $moves[] = $to;

                return true; // boleh lanjut sliding
            }
            if (self::colorOf($target) !== $color) {
                $moves[] = $to;
            }

            return false; // terblokir
        };

        $rank = intdiv($from, 8);
        $file = $from % 8;

        switch ($type) {
            case 'P':
                $dir = $color === 'w' ? -8 : 8;
                $startRank = $color === 'w' ? 6 : 1;

                $one = $from + $dir;
                if (self::onBoard($one) && $board[$one] === null) {
                    $moves[] = $one;
                    $two = $from + 2 * $dir;
                    if ($rank === $startRank && $board[$two] === null) {
                        $moves[] = $two;
                    }
                }

                foreach ([-1, 1] as $df) {
                    $to = $from + $dir + $df;
                    if (! self::onBoard($to) || abs(($to % 8) - $file) !== 1) {
                        continue;
                    }
                    $target = $board[$to];
                    if ($target !== null && self::colorOf($target) !== $color) {
                        $moves[] = $to;
                    }
                    if ($enPassant !== null && $to === $enPassant && $target === null) {
                        $moves[] = $to;
                    }
                }
                break;

            case 'N':
                foreach ([[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]] as [$df, $dr]) {
                    $nf = $file + $df;
                    $nr = $rank + $dr;
                    if ($nf >= 0 && $nf < 8 && $nr >= 0 && $nr < 8) {
                        $push($nr * 8 + $nf);
                    }
                }
                break;

            case 'B':
            case 'R':
            case 'Q':
                $dirs = match ($type) {
                    'B' => [[1, 1], [1, -1], [-1, 1], [-1, -1]],
                    'R' => [[0, 1], [0, -1], [1, 0], [-1, 0]],
                    default => [[0, 1], [0, -1], [1, 0], [-1, 0], [1, 1], [1, -1], [-1, 1], [-1, -1]],
                };
                foreach ($dirs as [$df, $dr]) {
                    for ($i = 1; $i < 8; $i++) {
                        $to = $from + $i * ($dr * 8 + $df);
                        $nf = ($to % 8) - ($from % 8);
                        // Patahkan pembungkus papan (wrap horizontal).
                        if (! self::onBoard($to) || abs($nf) !== $i * abs($df)) {
                            break;
                        }
                        if (! $push($to)) {
                            break;
                        }
                        if ($board[$to] !== null) {
                            break;
                        }
                    }
                }
                break;

            case 'K':
                foreach ([[0, 1], [0, -1], [1, 0], [-1, 0], [1, 1], [1, -1], [-1, 1], [-1, -1]] as [$df, $dr]) {
                    $nf = $file + $df;
                    $nr = $rank + $dr;
                    if ($nf >= 0 && $nf < 8 && $nr >= 0 && $nr < 8) {
                        $push($nr * 8 + $nf);
                    }
                }

                // Rokade: raja di home square, tidak sedang skak, kotak lintasan aman & kosong.
                $enemy = $color === 'w' ? 'b' : 'w';
                $home = $color === 'w' ? 60 : 4;

                if ($from === $home && ! self::isSquareAttacked($board, $from, $enemy)) {
                    $kingside = str_contains($castling, $color === 'w' ? 'K' : 'k');
                    $queenside = str_contains($castling, $color === 'w' ? 'Q' : 'q');

                    if ($kingside
                        && $board[$home + 1] === null
                        && $board[$home + 2] === null
                        && $board[$home + 3] === $color.'R'
                        && ! self::isSquareAttacked($board, $home + 1, $enemy)) {
                        $moves[] = $home + 2;
                    }

                    if ($queenside
                        && $board[$home - 1] === null
                        && $board[$home - 2] === null
                        && $board[$home - 3] === null
                        && $board[$home - 4] === $color.'R'
                        && ! self::isSquareAttacked($board, $home - 1, $enemy)) {
                        $moves[] = $home - 2;
                    }
                }
                break;
        }

        return $moves;
    }

    /**
     * Apakah kotak $index diserang oleh bidak warna $by?
     *
     * @param  array<int, ?string>  $board
     */
    public static function isSquareAttacked(array $board, int $index, string $by): bool
    {
        $rank = intdiv($index, 8);
        $file = $index % 8;

        // Pion: putih menyerang ke arah rank kecil (naik), hitam sebaliknya.
        $pawnRank = $by === 'w' ? $rank + 1 : $rank - 1;
        if ($pawnRank >= 0 && $pawnRank < 8) {
            foreach ([-1, 1] as $df) {
                $f = $file + $df;
                if ($f >= 0 && $f < 8 && $board[$pawnRank * 8 + $f] === $by.'P') {
                    return true;
                }
            }
        }

        // Kuda
        foreach ([[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]] as [$df, $dr]) {
            $r = $rank + $dr;
            $f = $file + $df;
            if ($r >= 0 && $r < 8 && $f >= 0 && $f < 8 && $board[$r * 8 + $f] === $by.'N') {
                return true;
            }
        }

        // Raja
        for ($dr = -1; $dr <= 1; $dr++) {
            for ($df = -1; $df <= 1; $df++) {
                if ($dr === 0 && $df === 0) {
                    continue;
                }
                $r = $rank + $dr;
                $f = $file + $df;
                if ($r >= 0 && $r < 8 && $f >= 0 && $f < 8 && $board[$r * 8 + $f] === $by.'K') {
                    return true;
                }
            }
        }

        // Bidak sliding: rook/queen (garis lurus) & bishop/queen (diagonal)
        $sliding = [
            [[[0, 1], [0, -1], [1, 0], [-1, 0]], 'R'],
            [[[1, 1], [1, -1], [-1, 1], [-1, -1]], 'B'],
        ];
        foreach ($sliding as [$dirs, $need]) {
            foreach ($dirs as [$df, $dr]) {
                for ($i = 1; $i < 8; $i++) {
                    $r = $rank + $dr * $i;
                    $f = $file + $df * $i;
                    if ($r < 0 || $r > 7 || $f < 0 || $f > 7) {
                        break;
                    }
                    $piece = $board[$r * 8 + $f];
                    if ($piece === null) {
                        continue;
                    }
                    $t = self::typeOf($piece);
                    if (self::colorOf($piece) === $by && ($t === $need || $t === 'Q')) {
                        return true;
                    }
                    break;
                }
            }
        }

        return false;
    }

    /**
     * Cari posisi raja suatu warna.
     *
     * @param  array<int, ?string>  $board
     */
    public static function kingSquare(array $board, string $color): ?int
    {
        foreach ($board as $i => $piece) {
            if ($piece === $color.'K') {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * Apakah warna $color sedang skak?
     *
     * @param  array<int, ?string>  $board
     */
    public static function inCheck(array $board, string $color): bool
    {
        $king = self::kingSquare($board, $color);

        return $king !== null && self::isSquareAttacked($board, $king, $color === 'w' ? 'b' : 'w');
    }

    /**
     * Terapkan langkah (asumsi pseudo-legal), kembalikan papan baru.
     * Menangani tangkapan, en passant, rokade, dan promosi otomatis ke ratu.
     *
     * @param  array<int, ?string>  $board
     * @return array{board: array<int, ?string>, captured: ?string}
     */
    public static function applyMove(array $board, int $from, int $to, ?int $enPassant): array
    {
        $piece = $board[$from];
        $color = $piece[0];
        $type = $piece[1];
        $captured = $board[$to];
        $newBoard = $board;

        // En passant: pion bergerak diagonal ke kotak kosong.
        if ($type === 'P' && $captured === null && abs(($to % 8) - ($from % 8)) === 1) {
            $captureSquare = $to + ($color === 'w' ? 8 : -8);
            $captured = $newBoard[$captureSquare] ?? null;
            $newBoard[$captureSquare] = null;
        }

        // Rokade: benteng ikut pindah.
        if ($type === 'K' && abs(($to % 8) - ($from % 8)) === 2) {
            $rank = intdiv($from, 8);
            if ($to % 8 === 6) { // kingside
                $newBoard[$rank * 8 + 5] = $newBoard[$rank * 8 + 7];
                $newBoard[$rank * 8 + 7] = null;
            } else { // queenside
                $newBoard[$rank * 8 + 3] = $newBoard[$rank * 8 + 0];
                $newBoard[$rank * 8 + 0] = null;
            }
        }

        $newBoard[$to] = $piece;
        $newBoard[$from] = null;

        // Promosi otomatis menjadi ratu.
        $promoRank = $color === 'w' ? 0 : 7;
        if ($type === 'P' && intdiv($to, 8) === $promoRank) {
            $newBoard[$to] = $color.'Q';
        }

        return ['board' => $newBoard, 'captured' => $captured];
    }

    /**
     * Hak rokade setelah satu langkah (hilang bila raja/benteng pindah atau tersapu).
     */
    public static function nextCastling(int $from, int $to, string $castling): string
    {
        // Putih: raja e1=60, benteng a1=56 / h1=63. Hitam: e8=4, a8=0 / h8=7.
        $map = [
            0 => ['q'], 4 => ['k', 'q'], 7 => ['k'],
            56 => ['Q'], 60 => ['K', 'Q'], 63 => ['K'],
        ];

        $lost = array_merge($map[$from] ?? [], $map[$to] ?? []);

        $result = '';
        foreach (str_split('KQkq') as $flag) {
            if (in_array($flag, $lost, true) || ! str_contains($castling, $flag)) {
                continue;
            }
            $result .= $flag;
        }

        return $result === '' ? '-' : $result;
    }

    /**
     * Kotak en-passant berikutnya bila pion melompat dua kotak.
     */
    public static function nextEnPassant(?string $piece, int $from, int $to): ?int
    {
        if ($piece === null || $piece[1] !== 'P' || abs($to - $from) !== 16) {
            return null;
        }

        return intdiv($from + $to, 2);
    }

    /**
     * Evaluasi akhir permainan untuk giliran $turn.
     *
     * @param  array<int, ?string>  $board
     * @return array{over: bool, winner: ?string, reason: ?string}
     */
    public static function gameStatus(array $board, string $turn, string $castling, ?int $enPassant, int $halfmove): array
    {
        // Aturan 50 langkah (100 halfmove tanpa tangkapan/gerak pion).
        if ($halfmove >= 100) {
            return ['over' => true, 'winner' => null, 'reason' => 'fifty_move'];
        }

        $hasLegal = false;
        foreach (self::allMoves($board, $turn, $castling, $enPassant) as $from => $targets) {
            foreach ($targets as $to) {
                $sim = self::applyMove($board, (int) $from, $to, $enPassant);
                if (! self::inCheck($sim['board'], $turn)) {
                    $hasLegal = true;
                    break 2;
                }
            }
        }

        if ($hasLegal) {
            return ['over' => false, 'winner' => null, 'reason' => null];
        }

        if (self::inCheck($board, $turn)) {
            return ['over' => true, 'winner' => $turn === 'w' ? 'b' : 'w', 'reason' => 'checkmate'];
        }

        return ['over' => true, 'winner' => null, 'reason' => 'stalemate'];
    }
}
