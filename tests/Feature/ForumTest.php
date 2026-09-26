<?php

namespace Tests\Feature;

use App\Livewire\Admin\Forum\Index as AdminForumIndex;
use App\Livewire\Resident\Forum\Index as ResidentForumIndex;
use App\Livewire\Resident\Forum\Show as ResidentForumShow;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ForumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(HousingSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@housinghub.id')->firstOrFail();
    }

    protected function residentUser(): User
    {
        return User::where('email', 'warga.a.1@housinghub.id')->firstOrFail();
    }

    protected function makePost(?User $author = null): Post
    {
        $author ??= $this->residentUser();

        $houseId = DB::table('house_residents')
            ->where('resident_id', $author->resident_id)
            ->where('status', 'active')
            ->orderByDesc('is_primary')
            ->value('house_id');

        $estateId = $houseId
            ? DB::table('houses')->where('id', $houseId)->value('housing_estate_id')
            : null;

        return Post::create([
            'housing_estate_id' => $estateId,
            'resident_id' => $author->resident_id,
            'user_id' => $author->id,
            'title' => 'Diskusi kondisi jalan blok A',
            'body' => 'Ada lubang di depan rumah nomor 3, mohon dibetulkan.',
            'category' => 'umum',
            'is_pinned' => false,
        ]);
    }

    protected function makeComment(Post $post): PostComment
    {
        $resident = $this->residentUser();

        return PostComment::create([
            'post_id' => $post->id,
            'resident_id' => $resident->resident_id,
            'user_id' => $resident->id,
            'body' => 'Setuju, mohon segera diperbaiki.',
        ]);
    }

    public function test_admin_forum_page_renders_with_moderation_actions(): void
    {
        $post = $this->makePost();
        $comment = $this->makeComment($post);

        $this->actingAs($this->admin())->get(route('admin.forum.index'))
            ->assertOk()
            ->assertSee('Moderasi Forum', false)
            ->assertSee($post->title)
            ->assertSee('Sematkan')
            ->assertSee('Komentar Terbaru')
            ->assertSee($comment->body);
    }

    public function test_resident_cannot_open_admin_forum(): void
    {
        $this->actingAs($this->residentUser())
            ->get(route('admin.forum.index'))
            ->assertForbidden();
    }

    public function test_resident_can_create_post_and_see_it_in_forum(): void
    {
        $resident = $this->residentUser();

        Livewire::actingAs($resident)
            ->test(ResidentForumIndex::class)
            ->set('title', 'Lomba kebersihan RT')
            ->set('category', 'kegiatan')
            ->set('body', 'Ayo ikut lomba kebersihan akhir pekan ini.')
            ->call('submit')
            ->assertRedirect();

        $post = Post::where('title', 'Lomba kebersihan RT')->firstOrFail();
        $this->assertSame($resident->resident_id, $post->resident_id);

        $this->actingAs($resident)->get(route('resident.forum.index'))
            ->assertOk()
            ->assertSee('Lomba kebersihan RT');

        $this->actingAs($resident)->get(route('resident.forum.show', $post))
            ->assertOk()
            ->assertSee('Ayo ikut lomba kebersihan akhir pekan ini.');
    }

    public function test_resident_can_comment_on_a_post(): void
    {
        $post = $this->makePost();
        $resident = $this->residentUser();

        Livewire::actingAs($resident)
            ->test(ResidentForumShow::class, ['post' => $post])
            ->set('body', 'Jalan depan rumah saya juga rusak.')
            ->call('addComment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('post_comments', [
            'post_id' => $post->id,
            'resident_id' => $resident->resident_id,
            'body' => 'Jalan depan rumah saya juga rusak.',
        ]);
    }

    public function test_admin_can_pin_post_and_delete_comment(): void
    {
        $post = $this->makePost();
        $comment = $this->makeComment($post);

        Livewire::actingAs($this->admin())
            ->test(AdminForumIndex::class)
            ->call('togglePin', $post->id);

        $this->assertTrue($post->fresh()->is_pinned);

        Livewire::actingAs($this->admin())
            ->test(AdminForumIndex::class)
            ->call('deleteComment', $comment->id);

        $this->assertSoftDeleted('post_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_delete_post_with_its_comments(): void
    {
        $post = $this->makePost();
        $comment = $this->makeComment($post);

        Livewire::actingAs($this->admin())
            ->test(AdminForumIndex::class)
            ->call('deletePost', $post->id);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertSoftDeleted('post_comments', ['id' => $comment->id]);
    }

    public function test_forum_menu_follows_permission(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.forum.index'), false);

        $financeRole = Role::where('slug', 'finance')->firstOrFail();
        $finance = User::factory()->create(['role_id' => $financeRole->id, 'status' => 'active']);

        $this->actingAs($finance)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.forum.index'), false);
    }
}
