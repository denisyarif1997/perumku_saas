<?php

namespace Tests\Feature;

use App\Livewire\Admin\Forum\Index as AdminForumIndex;
use App\Livewire\Resident\Forum\Index as ResidentForumIndex;
use App\Livewire\Resident\Forum\Show as ResidentForumShow;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\HousingBlock;
use App\Models\HousingEstate;
use App\Models\Post;
use App\Models\PostPollVote;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ForumPollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function resident(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Admin estate A yang memegang permission manage-forum (bukan super_admin). */
    protected function forumModerator(): User
    {
        $estateId = HousingEstate::where('code', 'HH-01')->value('id');

        $role = Role::where('slug', '!=', 'super_admin')
            ->whereHas('permissions', fn ($query) => $query->where('slug', 'manage-forum'))
            ->firstOrFail();

        return User::create([
            'name' => 'Moderator Forum',
            'email' => 'moderator.forum@housinghub.test',
            'password' => 'password',
            'role_id' => $role->id,
            'housing_estate_id' => $estateId,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<int, string>  $choices
     * @param  array<string, mixed>  $overrides
     */
    protected function makePoll(
        ?User $author = null,
        array $choices = ['Setuju', 'Tidak setuju'],
        string $type = 'single',
        array $overrides = [],
    ): Post {
        $author ??= $this->resident('warga.a.1@housinghub.id');

        $houseId = DB::table('house_residents')
            ->where('resident_id', $author->resident_id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->value('house_id');

        $options = collect($choices)
            ->values()
            ->map(fn (string $label, int $index) => ['key' => 'o'.($index + 1), 'label' => $label])
            ->all();

        return Post::create([
            'housing_estate_id' => $houseId
                ? DB::table('houses')->where('id', $houseId)->value('housing_estate_id')
                : null,
            'resident_id' => $author->resident_id,
            'user_id' => $author->id,
            'title' => 'Jam operasional pos keamanan',
            'body' => 'Mohon suara warga untuk menentukan jam piket.',
            'category' => 'umum',
            'is_pinned' => false,
            'is_poll' => true,
            'poll_type' => $type,
            'poll_options' => $options,
            'poll_closes_at' => now()->addDays(3),
            'poll_is_closed' => false,
            ...$overrides,
        ]);
    }

    /** Estate kedua lengkap dengan warga aktifnya, untuk uji isolasi tenant. */
    protected function makeEstateB(): array
    {
        $estate = HousingEstate::withoutGlobalScope(BelongsToEstateScope::class)->firstOrCreate(
            ['code' => 'HH-002'],
            ['name' => 'Perumah Sejahtera']
        );

        $block = HousingBlock::firstOrCreate(
            ['housing_estate_id' => $estate->id, 'code' => 'B'],
            ['name' => 'Blok B', 'status' => 'active']
        );

        $house = House::firstOrCreate(
            ['housing_block_id' => $block->id, 'house_number' => 'B-01'],
            ['housing_estate_id' => $estate->id, 'status' => 'active']
        );

        $resident = Resident::firstOrCreate(
            ['nik' => '3273010102000002'],
            ['name' => 'Warga Estate B', 'gender' => 'male', 'status' => 'active']
        );

        HouseResident::firstOrCreate(
            ['house_id' => $house->id, 'resident_id' => $resident->id],
            [
                'relationship' => 'owner',
                'is_owner' => true,
                'is_primary' => true,
                'start_date' => now()->subYear()->toDateString(),
                'status' => 'active',
            ]
        );

        $user = User::firstOrCreate(
            ['email' => 'warga.b@housinghub.test'],
            [
                'name' => 'Warga B',
                'password' => 'password',
                'role_id' => Role::where('slug', 'resident')->value('id'),
                'resident_id' => $resident->id,
                'housing_estate_id' => $estate->id,
                'status' => 'active',
            ]
        );

        return [$estate, $house, $resident, $user];
    }

    public function test_resident_can_create_a_poll_post(): void
    {
        Livewire::actingAs($this->resident('warga.a.1@housinghub.id'))
            ->test(ResidentForumIndex::class)
            ->set('title', 'Lomba kebersihan RT')
            ->set('category', 'kegiatan')
            ->set('body', 'Ayo ikut lomba kebersihan akhir pekan ini.')
            ->set('isPoll', true)
            ->set('pollType', 'multiple')
            ->set('pollDuration', '7')
            ->set('pollChoices', ['Sabtu pagi', 'Sabtu sore'])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $post = Post::where('title', 'Lomba kebersihan RT')->firstOrFail();

        $this->assertTrue($post->is_poll);
        $this->assertSame('multiple', $post->poll_type);
        $this->assertSame(['o1' => 'Sabtu pagi', 'o2' => 'Sabtu sore'], $post->pollOptions());
        $this->assertTrue($post->isPollOpen());
        $this->assertSame(
            now()->addDays(7)->toDateTimeString(),
            $post->poll_closes_at->toDateTimeString(),
        );
    }

    public function test_poll_requires_two_distinct_choices(): void
    {
        Livewire::actingAs($this->resident('warga.a.1@housinghub.id'))
            ->test(ResidentForumIndex::class)
            ->set('title', 'Polling cacat')
            ->set('body', 'Isi polling.')
            ->set('isPoll', true)
            ->set('pollChoices', ['Setuju', 'Setuju'])
            ->call('submit')
            ->assertHasErrors('pollChoices');

        $this->assertDatabaseMissing('posts', ['title' => 'Polling cacat']);
    }

    public function test_resident_can_build_the_poll_inside_the_composer(): void
    {
        Livewire::actingAs($this->resident('warga.a.1@housinghub.id'))
            ->test(ResidentForumIndex::class)
            ->call('openForm')
            ->set('isPoll', true)
            ->assertSee('Mode Polling')
            ->call('addPollChoice')
            ->call('addPollChoice')
            ->assertSet('pollChoices', ['', '', '', ''])
            ->call('removePollChoice', 3)
            ->assertSet('pollChoices', ['', '', ''])
            // Dua pilihan terakhir tidak boleh dihapus.
            ->call('removePollChoice', 1)
            ->assertSet('pollChoices', ['', ''])
            ->call('disablePoll')
            ->assertSet('isPoll', false)
            ->assertSee('Jadikan Polling')
            // Binding per-index pada input teks composer (wire:model="pollChoices.N")
            // juga harus tetap berupa array.
            ->set('isPoll', true)
            ->set('pollChoices.0', 'Ya')
            ->set('pollChoices.1', 'Tidak')
            ->assertSet('pollChoices', ['Ya', 'Tidak']);
    }

    public function test_poll_rejects_a_blank_choice(): void
    {
        Livewire::actingAs($this->resident('warga.a.1@housinghub.id'))
            ->test(ResidentForumIndex::class)
            ->set('title', 'Polling belum lengkap')
            ->set('body', 'Satu pilihan masih kosong.')
            ->set('isPoll', true)
            ->set('pollChoices', ['Setuju', ' '])
            ->call('submit')
            ->assertHasErrors('pollChoices.1');

        $this->assertDatabaseMissing('posts', ['title' => 'Polling belum lengkap']);
    }

    public function test_forum_feed_shows_the_poll_card_and_voter_count(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o2'])
            ->call('vote');

        $this->actingAs($this->findUserUnscoped('warga.a.1@housinghub.id'))
            ->get(route('resident.forum.index'))
            ->assertOk()
            ->assertSee('Polling')
            ->assertSee('Berlangsung')
            ->assertSee('Tidak setuju')
            ->assertSee('1 warga sudah memilih')
            ->assertSee('Ikut Polling');
    }

    public function test_radio_selection_keeps_selected_options_an_array(): void
    {
        $post = $this->makePoll();

        // Regresi: wire:model pada radio mengirim string, yang tidak bisa masuk
        // ke properti array $selectedOptions. Pilihan lewat aksi selectOption.
        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->assertSet('selectedOptions', [])
            ->call('selectOption', 'o1')
            ->assertSet('selectedOptions', ['o1'])
            ->call('selectOption', 'o2')
            ->assertSet('selectedOptions', ['o2'])
            ->call('vote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'option_key' => 'o2',
        ]);
        $this->assertDatabaseMissing('post_poll_votes', [
            'post_id' => $post->id,
            'option_key' => 'o1',
        ]);
    }

    public function test_multiple_choice_selection_toggles_each_option(): void
    {
        $post = $this->makePoll(choices: ['Senin', 'Selasa', 'Rabu'], type: 'multiple');
        $voter = $this->resident('warga.b.1@housinghub.id');

        $component = Livewire::actingAs($voter)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('selectOption', 'o1')
            ->assertSet('selectedOptions', ['o1'])
            ->call('selectOption', 'o3')
            ->assertSet('selectedOptions', ['o1', 'o3'])
            // Menekan ulang pilihan yang sama melepasnya.
            ->call('selectOption', 'o1')
            ->assertSet('selectedOptions', ['o3'])
            ->call('vote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o3',
        ]);
        $this->assertDatabaseMissing('post_poll_votes', [
            'post_id' => $post->id,
            'option_key' => 'o1',
        ]);

        // Batal mengembalikan ke suara yang benar-benar tersimpan.
        $component->call('editVote')
            ->call('selectOption', 'o1')
            ->call('cancelVoteEdit')
            ->assertSet('editingVote', false)
            ->assertSet('selectedOptions', ['o3']);
    }

    public function test_selecting_an_unknown_or_closed_option_is_ignored(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('selectOption', 'o9')
            ->assertSet('selectedOptions', []);

        $closed = $this->makePoll(overrides: ['poll_is_closed' => true]);

        Livewire::actingAs($this->resident('warga.c.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $closed])
            ->call('selectOption', 'o1')
            ->assertSet('selectedOptions', []);
    }

    public function test_resident_can_vote_on_an_open_poll(): void
    {
        $post = $this->makePoll();
        $voter = $this->resident('warga.b.1@housinghub.id');

        Livewire::actingAs($voter)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote')
            ->assertHasNoErrors()
            ->assertSet('selectedOptions', ['o1'])
            ->assertSet('editingVote', false);

        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o1',
        ]);
    }

    public function test_poll_results_are_hidden_until_the_resident_votes(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->assertViewHas('canViewResults', false)
            ->assertViewHas('pollResults', [])
            ->assertSee('Hasil polling tampil setelah Anda memilih.');
    }

    public function test_poll_results_appear_after_voting(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote')
            ->assertViewHas('canViewResults', true)
            ->assertSee('100% · 1 suara')
            ->assertDontSee('Hasil polling tampil setelah Anda memilih.');
    }

    public function test_poll_author_and_moderator_see_results_without_voting(): void
    {
        $post = $this->makePoll();
        $author = $this->resident('warga.a.1@housinghub.id');

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote')
            ->assertHasNoErrors();

        $this->assertTrue(Livewire::actingAs($author)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->viewData('canViewResults'));

        $this->assertTrue(Livewire::actingAs($this->forumModerator())
            ->test(ResidentForumShow::class, ['post' => $post])
            ->viewData('canViewResults'));
    }

    public function test_resident_can_change_their_vote_while_the_poll_is_open(): void
    {
        $post = $this->makePoll();
        $voter = $this->resident('warga.b.1@housinghub.id');

        $component = Livewire::actingAs($voter)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote');

        $component->call('editVote')->assertSet('editingVote', true);

        $component->set('selectedOptions', ['o2'])->call('vote');

        $this->assertDatabaseMissing('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o1',
        ]);
        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o2',
        ]);
    }

    public function test_single_choice_poll_rejects_more_than_one_selection(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1', 'o2'])
            ->call('vote')
            ->assertHasErrors('selectedOptions');

        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_multiple_choice_poll_accepts_several_options(): void
    {
        $post = $this->makePoll(choices: ['Senin', 'Selasa', 'Rabu'], type: 'multiple');
        $voter = $this->resident('warga.b.1@housinghub.id');

        Livewire::actingAs($voter)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1', 'o3'])
            ->call('vote')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o1',
        ]);
        $this->assertDatabaseHas('post_poll_votes', [
            'post_id' => $post->id,
            'resident_id' => $voter->resident_id,
            'option_key' => 'o3',
        ]);
    }

    public function test_vote_rejects_an_option_that_is_not_in_the_poll(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o9'])
            ->call('vote')
            ->assertHasErrors('selectedOptions.0');

        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_poll_author_can_close_and_reopen_the_poll(): void
    {
        $post = $this->makePoll();
        $author = $this->resident('warga.a.1@housinghub.id');

        Livewire::actingAs($author)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('closePoll');

        $this->assertFalse($post->fresh()->isPollOpen());

        Livewire::actingAs($author)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('closePoll');

        $this->assertTrue($post->fresh()->isPollOpen());
    }

    public function test_vote_is_rejected_after_the_poll_is_closed(): void
    {
        $post = $this->makePoll(overrides: ['poll_is_closed' => true]);

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->assertSee('Ditutup')
            ->set('selectedOptions', ['o1'])
            ->call('vote')
            ->assertHasErrors('selectedOptions')
            ->assertSee('Polling sudah ditutup. Hasil hanya dilihat warga yang sudah memilih.');

        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_vote_is_rejected_after_the_deadline_passes(): void
    {
        $post = $this->makePoll(overrides: ['poll_closes_at' => now()->subMinute()]);

        $this->assertFalse($post->isPollOpen());

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote')
            ->assertHasErrors('selectedOptions');

        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_another_resident_cannot_close_someone_elses_poll(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('closePoll')
            ->assertForbidden();

        $this->assertFalse($post->fresh()->poll_is_closed);
    }

    public function test_moderator_can_close_a_poll_from_the_forum_moderation_page(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->forumModerator())
            ->test(AdminForumIndex::class)
            ->call('togglePoll', $post->id);

        $this->assertTrue($post->fresh()->poll_is_closed);
    }

    public function test_deleting_a_poll_post_also_removes_its_votes(): void
    {
        $post = $this->makePoll();

        Livewire::actingAs($this->resident('warga.b.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('selectedOptions', ['o1'])
            ->call('vote');

        Livewire::actingAs($this->resident('warga.a.1@housinghub.id'))
            ->test(ResidentForumShow::class, ['post' => $post])
            ->call('deletePost');

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseCount('post_poll_votes', 0);
    }

    public function test_poll_votes_are_not_visible_from_another_estate(): void
    {
        [, , , $wargaB] = $this->makeEstateB();

        $postB = Post::create([
            'housing_estate_id' => $wargaB->housing_estate_id,
            'resident_id' => $wargaB->resident_id,
            'user_id' => $wargaB->id,
            'title' => 'Polling estate B',
            'body' => 'Hanya untuk warga estate B.',
            'is_poll' => true,
            'poll_type' => 'single',
            'poll_options' => [['key' => 'o1', 'label' => 'Ya'], ['key' => 'o2', 'label' => 'Tidak']],
            'poll_closes_at' => now()->addDays(3),
        ]);

        $this->actingAs($wargaB);

        $postB->votes()->create([
            'resident_id' => $wargaB->resident_id,
            'user_id' => $wargaB->id,
            'option_key' => 'o1',
        ]);

        $this->actingAs($this->findUserUnscoped('warga.a.1@housinghub.id'));

        $this->assertNull(PostPollVote::query()->where('post_id', $postB->id)->first());
        $this->assertSame(1, $this->findVoteUnscoped($postB)->count());
        $this->assertSame(0, $postB->pollVotersCount());

        $this->actingAs($wargaB);

        $this->assertSame(1, $postB->pollVotersCount());
    }

    /**
     * Ambil suara polling tanpa filter estate, untuk membuktikan bahwa baris
     * tetap ada tetapi tidak terlihat dari sesi estate lain.
     *
     * @return Collection<int, PostPollVote>
     */
    protected function findVoteUnscoped(Post $post)
    {
        return PostPollVote::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('post_id', $post->id)
            ->get();
    }
}
