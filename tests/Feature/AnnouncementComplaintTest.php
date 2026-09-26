<?php

namespace Tests\Feature;

use App\Livewire\Admin\Info\Announcements;
use App\Livewire\Admin\Info\ComplaintDetail;
use App\Livewire\Resident\Complaints\Index;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\HousingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementComplaintTest extends TestCase
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
        return $this->findUserUnscoped('admin@housinghub.id');
    }

    protected function residentUser(): User
    {
        return $this->findUserUnscoped('warga.a.1@housinghub.id');
    }

    protected function makeAnnouncement(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'user_id' => $this->admin()->id,
            'title' => 'Pengumuman Uji',
            'content' => 'Isi pengumuman uji.',
            'category' => 'general',
            'priority' => 'normal',
            'is_pinned' => false,
            'status' => 'published',
        ], $overrides));
    }

    public function test_resident_info_shows_only_published_and_unexpired_announcements(): void
    {
        $published = $this->makeAnnouncement(['title' => 'Terkini']);
        $this->makeAnnouncement(['title' => 'Draf', 'status' => 'draft']);
        $this->makeAnnouncement(['title' => 'Arsip', 'status' => 'archived']);
        $this->makeAnnouncement(['title' => 'Kedaluwarsa', 'expired_at' => now()->subDay()]);

        $response = $this->actingAs($this->residentUser())->get(route('resident.info.index'));

        $response->assertOk();
        $response->assertSee('Terkini');
        $response->assertDontSee('Draf');
        $response->assertDontSee('Kedaluwarsa');

        $this->assertTrue(Announcement::published()->get()->contains($published->id));
    }

    public function test_guest_is_redirected_from_resident_info(): void
    {
        $this->get(route('resident.info.index'))->assertRedirect(route('login'));
    }

    public function test_resident_cannot_access_admin_announcement_panel(): void
    {
        $this->actingAs($this->residentUser())
            ->get(route('admin.info.announcements'))
            ->assertForbidden();
    }

    public function test_admin_can_create_publish_and_archive_announcement(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Announcements::class)
            ->set('title', 'Pemeliharaan Air Rutin')
            ->set('body', 'Rumah A.1 akan mendapat pemeliharaan air pada Sabtu.')
            ->set('category', 'maintenance')
            ->set('priority', 'high')
            ->call('save');

        $announcement = Announcement::where('title', 'Pemeliharaan Air Rutin')->firstOrFail();
        $this->assertSame('published', $announcement->status);
        $this->assertNotNull($announcement->user_id);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'announcements',
            'action' => 'create',
            'subject_id' => $announcement->id,
        ]);

        // Arsipkan.
        Livewire::actingAs($this->admin())
            ->test(Announcements::class)
            ->call('archive', $announcement->id);

        $this->assertSame('archived', $announcement->fresh()->status);
    }

    public function test_admin_announcement_validation_rejects_empty_title(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Announcements::class)
            ->set('title', '')
            ->set('body', 'Isi cukup.')
            ->call('save')
            ->assertHasErrors(['title']);

        $this->assertSame(0, Announcement::count());
    }

    public function test_resident_can_create_complaint_with_ticket_number_and_log(): void
    {
        $resident = $this->residentUser();

        $component = Livewire::actingAs($resident)
            ->test(Index::class)
            ->set('title', 'Lampu Taman Padam')
            ->set('description', 'Lampu taman dekat rumah A.1 padam sejak kemarin.')
            ->set('category', 'electricity')
            ->set('priority', 'high')
            ->call('submit');

        $complaint = Complaint::where('title', 'Lampu Taman Padam')->firstOrFail();

        $this->assertSame('open', $complaint->status);
        $this->assertNotNull($complaint->ticket_number);
        $this->assertStringStartsWith('ADU/', $complaint->ticket_number);
        $this->assertSame($resident->resident_id, $complaint->resident_id);
        $this->assertNotNull($complaint->house_id);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'complaints',
            'action' => 'create',
            'subject_id' => $complaint->id,
        ]);

        $component->assertRedirect(route('resident.complaints.show', $complaint));
    }

    public function test_resident_complaint_validation_rejects_empty_title(): void
    {
        Livewire::actingAs($this->residentUser())
            ->test(Index::class)
            ->set('title', '')
            ->set('description', 'Deskripsi.')
            ->call('submit')
            ->assertHasErrors(['title']);

        $this->assertSame(0, Complaint::count());
    }

    public function test_resident_cannot_view_other_residents_complaint(): void
    {
        $mine = $this->residentUser();
        $other = User::where('email', 'like', 'warga.b.%')->where('id', '!=', $mine->id)->firstOrFail();

        $otherComplaint = Complaint::create([
            'resident_id' => $other->resident_id,
            'title' => 'Pengaduan Tetangga',
            'description' => 'Deskripsi pengaduan tetangga.',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($mine)->get(route('resident.complaints.show', $otherComplaint))->assertForbidden();
        $this->actingAs($other)->get(route('resident.complaints.show', $otherComplaint))->assertOk();
    }

    public function test_admin_response_moves_open_complaint_to_in_progress(): void
    {
        $complaint = $this->makeComplaint();

        Livewire::actingAs($this->admin())
            ->test(ComplaintDetail::class, ['complaint' => $complaint])
            ->set('response_message', 'Petugas akan datang hari ini.')
            ->call('saveResponse');

        $this->assertDatabaseHas('complaint_responses', [
            'complaint_id' => $complaint->id,
            'is_internal' => false,
        ]);
        $this->assertSame('in_progress', $complaint->fresh()->status);

        // Tanggapan internal tidak mengubah status lagi.
        Livewire::actingAs($this->admin())
            ->test(ComplaintDetail::class, ['complaint' => $complaint])
            ->set('response_message', 'Catatan internal staf.')
            ->set('is_internal', true)
            ->call('saveResponse');

        $this->assertSame('in_progress', $complaint->fresh()->status);
    }

    public function test_admin_resolves_complaint_and_sets_resolver(): void
    {
        $complaint = $this->makeComplaint();

        Livewire::actingAs($this->admin())
            ->test(ComplaintDetail::class, ['complaint' => $complaint])
            ->set('status', 'resolved')
            ->set('resolution_note', 'Sudah diperbaiki petugas.')
            ->call('updateStatus');

        $fresh = $complaint->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertSame($this->admin()->id, $fresh->resolved_by);
    }

    public function test_resident_cannot_access_admin_complaint_detail(): void
    {
        $complaint = $this->makeComplaint();

        $this->actingAs($this->residentUser())
            ->get(route('admin.info.complaints.show', $complaint))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->get(route('admin.info.complaints.show', $complaint))
            ->assertOk();

        $this->assertFalse($this->residentUser()->hasPermission('manage-complaint'));
        $this->assertTrue($this->admin()->hasPermission('manage-complaint'));
    }

    public function test_resident_complaints_index_lists_only_own_complaints(): void
    {
        $mine = $this->residentUser();
        $other = User::where('email', 'like', 'warga.b.%')->where('id', '!=', $mine->id)->firstOrFail();

        Complaint::create([
            'resident_id' => $mine->resident_id,
            'title' => 'Pengaduan Sendiri',
            'description' => 'Deskripsi.',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        Complaint::create([
            'resident_id' => $other->resident_id,
            'title' => 'PengaduanOrangLainXYZ',
            'description' => 'Deskripsi.',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($mine)->get(route('resident.complaints.index'))
            ->assertOk()
            ->assertSee('Pengaduan Sendiri')
            ->assertDontSee('PengaduanOrangLainXYZ');
    }

    public function test_resident_forum_index_loads_and_shows_posts(): void
    {
        $resident = $this->residentUser();

        Post::create([
            'housing_estate_id' => $resident->resident?->housing_estate_id,
            'resident_id' => $resident->resident_id,
            'user_id' => $resident->id,
            'title' => 'Forum warga test',
            'body' => 'Isi forum warga test.',
            'category' => 'umum',
            'is_pinned' => false,
        ]);

        $this->actingAs($resident)
            ->get(route('resident.forum.index'))
            ->assertOk()
            ->assertSee('Forum Warga')
            ->assertSee('Forum warga test');
    }

    protected function makeComplaint(): Complaint
    {
        // ticket_number terisi otomatis oleh hook created pada model.
        return Complaint::create([
            'resident_id' => $this->residentUser()->resident_id,
            'title' => 'Bocor di Belakang',
            'description' => 'Pipa belakang rumah bocor.',
            'category' => 'water',
            'priority' => 'high',
            'status' => 'open',
        ]);
    }
}
