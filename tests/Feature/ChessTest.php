<?php

namespace Tests\Feature;

use App\Livewire\Resident\Chess\Index as ResidentChessIndex;
use App\Livewire\Resident\Chess\Play as ResidentChessPlay;
use App\Models\ChessGame;
use App\Models\User;
use App\Support\ChessEngine;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function residentUsers(): array
    {
        return User::whereNotNull('resident_id')->where('status', 'active')
            ->orderBy('id')->limit(2)->get()->all();
    }

    public function test_resident_can_create_open_challenge_and_other_resident_accepts(): void
    {
        [$host, $guest] = $this->residentUsers();

        Livewire::actingAs($host)
            ->test(ResidentChessIndex::class)
            ->call('createOpenChallenge')
            ->assertOk();

        $game = ChessGame::query()->latest('id')->firstOrFail();
        $this->assertTrue($game->isPending());
        $this->assertSame($host->id, $game->inviter_user_id);
        $this->assertSame(ChessEngine::initialBoard(), $game->board);

        Livewire::actingAs($guest)
            ->test(ResidentChessIndex::class)
            ->call('accept', $game->id)
            ->assertRedirect();

        $game->refresh();
        $this->assertTrue($game->isActive());
        $this->assertSame($host->id, $game->white_user_id);
        $this->assertSame($guest->id, $game->black_user_id);
    }

    public function test_players_can_move_legally_and_checkmate_ends_game(): void
    {
        [$host, $guest] = $this->residentUsers();

        $game = ChessGame::createChallenge($host->id, $guest->id);
        $game->accept($host->id);

        // Scholar's mate ringkas: e2-e4, e7-e5, Qd1-h5, Nb8-c6, Bf1-c4, Ng8-f6, Qh5xf7#
        $moves = [
            [$host, 52, 36],
            [$guest, 12, 28],
            [$host, 59, 31],
            [$guest, 1, 18],
            [$host, 61, 34],
            [$guest, 6, 21],
            [$host, 31, 13],
        ];

        foreach ($moves as $i => [$player, $from, $to]) {
            $component = Livewire::actingAs($player)
                ->test(ResidentChessPlay::class, ['game' => $game->fresh()])
                ->call('tapSquare', $from)
                ->call('tapSquare', $to);

            if ($i === count($moves) - 1) {
                $component->assertOk();
            }
        }

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertSame('w', $game->winner);
        $this->assertSame('checkmate', $game->end_reason);
    }

    public function test_illegal_move_is_rejected_and_turn_cannot_be_skipped(): void
    {
        [$host, $guest] = $this->residentUsers();

        $game = ChessGame::createChallenge($host->id, $guest->id);
        $game->accept($host->id);

        // Giliran putih, bukan hitam.
        Livewire::actingAs($guest)
            ->test(ResidentChessPlay::class, ['game' => $game->fresh()])
            ->call('tapSquare', 12)
            ->assertOk();

        $game->refresh();
        $this->assertSame('w', $game->turn);

        // Langkah tidak valid: pion e2 tidak bisa ke e5.
        Livewire::actingAs($host)
            ->test(ResidentChessPlay::class, ['game' => $game->fresh()])
            ->call('tapSquare', 52)
            ->call('tapSquare', 28)
            ->assertOk();

        $game->refresh();
        $this->assertSame('w', $game->turn);
        $this->assertSame(ChessEngine::initialBoard(), $game->board);
    }

    public function test_resign_ends_game_with_opponent_as_winner(): void
    {
        [$host, $guest] = $this->residentUsers();

        $game = ChessGame::createChallenge($host->id, $guest->id);
        $game->accept($host->id);

        Livewire::actingAs($guest)
            ->test(ResidentChessPlay::class, ['game' => $game->fresh()])
            ->call('resign')
            ->assertOk();

        $game->refresh();
        $this->assertSame('finished', $game->status);
        $this->assertSame('w', $game->winner);
        $this->assertSame('resign', $game->end_reason);
    }

    public function test_personal_invite_flow(): void
    {
        [$host, $guest] = $this->residentUsers();

        Livewire::actingAs($host)
            ->test(ResidentChessIndex::class)
            ->call('invite', $guest->id)
            ->assertOk();

        $game = ChessGame::query()->latest('id')->firstOrFail();
        $this->assertTrue($game->isPending());
        $this->assertSame($guest->id, $game->invitee_user_id);

        // Warga lain tidak bisa menerima undangan personal.
        $third = User::whereNotNull('resident_id')->where('status', 'active')
            ->whereNotIn('id', [$host->id, $guest->id])->first();

        if ($third) {
            Livewire::actingAs($third)
                ->test(ResidentChessIndex::class)
                ->call('accept', $game->id)
                ->assertOk();

            $game->refresh();
            $this->assertTrue($game->isPending());
        }

        Livewire::actingAs($guest)
            ->test(ResidentChessIndex::class)
            ->call('accept', $game->id)
            ->assertRedirect();

        $game->refresh();
        $this->assertTrue($game->isActive());
    }
}
