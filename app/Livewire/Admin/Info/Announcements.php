<?php

namespace App\Livewire\Admin\Info;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncement;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Announcements extends Component
{
    use WithPagination;

    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $category = 'general';

    public string $priority = 'normal';

    public bool $is_pinned = false;

    public string $published_at = '';

    public string $expired_at = '';

    public string $status = 'published';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);
        $this->published_at = now()->format('Y-m-d\TH:i');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);

        $item = Announcement::findOrFail($id);
        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->body = $item->content;
        $this->category = $item->category;
        $this->priority = $item->priority;
        $this->is_pinned = (bool) $item->is_pinned;
        $this->published_at = $item->published_at?->format('Y-m-d\TH:i') ?? '';
        $this->expired_at = $item->expired_at?->format('Y-m-d\TH:i') ?? '';
        $this->status = $item->status;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'body', 'expired_at', 'is_pinned']);
        $this->category = 'general';
        $this->priority = 'normal';
        $this->status = 'published';
        $this->published_at = now()->format('Y-m-d\TH:i');
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);

        $data = $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'in:general,maintenance,event,security,billing,urgent'],
            'priority' => ['required', 'in:low,normal,high'],
            'is_pinned' => ['boolean'],
            'published_at' => ['required', 'date'],
            'expired_at' => ['nullable', 'date', 'after_or_equal:published_at'],
            'status' => ['required', 'in:draft,published,archived'],
        ], [
            'title.required' => 'Judul pengumuman wajib diisi.',
            'body.required' => 'Isi pengumuman wajib diisi.',
            'expired_at.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal terbit.',
        ]);

        $payload = [
            'title' => $data['title'],
            'content' => $data['body'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'is_pinned' => $this->is_pinned,
            'published_at' => $data['published_at'],
            'expired_at' => $data['expired_at'] ?: null,
            'status' => $data['status'],
        ];

        if ($this->editingId) {
            $item = Announcement::findOrFail($this->editingId);
            $old = $item->toArray();
            $item->update($payload);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'announcements',
                'subject_type' => Announcement::class, 'subject_id' => $item->id,
                'description' => 'Mengubah pengumuman: '.$item->title,
                'old_values' => $old, 'new_values' => $item->fresh()->toArray(),
            ]);

            // Pengumuman yang baru berstatus terbit → notifikasi ke warga.
            if (($old['status'] ?? null) !== 'published') {
                $this->notifyResidents($item);
            }

            session()->flash('success', 'Pengumuman berhasil diubah.');
        } else {
            $item = Announcement::create($payload + ['user_id' => auth()->id()]);

            ActivityLog::record([
                'user_id' => auth()->id(), 'action' => 'create', 'module' => 'announcements',
                'subject_type' => Announcement::class, 'subject_id' => $item->id,
                'description' => 'Menambah pengumuman: '.$item->title,
                'new_values' => $item->toArray(),
            ]);

            if ($item->status === 'published') {
                $this->notifyResidents($item);
            }

            session()->flash('success', 'Pengumuman berhasil ditambah.');
        }

        $this->cancel();
    }

    public function publish(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);

        $item = Announcement::findOrFail($id);
        $wasPublished = $item->status === 'published';
        $item->update(['status' => 'published', 'published_at' => $item->published_at ?? now()]);

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'publish', 'module' => 'announcements',
            'subject_type' => Announcement::class, 'subject_id' => $item->id,
            'description' => 'Menerbitkan pengumuman: '.$item->title,
            'new_values' => $item->fresh()->toArray(),
        ]);

        if (! $wasPublished) {
            $this->notifyResidents($item);
        }

        session()->flash('success', 'Pengumuman diterbitkan.');
    }

    public function archive(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);

        $item = Announcement::findOrFail($id);
        $item->update(['status' => 'archived']);

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'archive', 'module' => 'announcements',
            'subject_type' => Announcement::class, 'subject_id' => $item->id,
            'description' => 'Mengarsipkan pengumuman: '.$item->title,
        ]);

        session()->flash('success', 'Pengumuman diarsipkan.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('manage-announcement'), 403);

        $item = Announcement::findOrFail($id);
        $old = $item->toArray();
        $item->delete();

        ActivityLog::record([
            'user_id' => auth()->id(), 'action' => 'delete', 'module' => 'announcements',
            'subject_type' => Announcement::class, 'subject_id' => $id,
            'description' => 'Menghapus pengumuman: '.$item->title,
            'old_values' => $old,
        ]);

        if ($this->editingId === $id) {
            $this->cancel();
        }

        session()->flash('success', 'Pengumuman dihapus.');
    }

    /**
     * Kirim notifikasi in-app ke warga estate terkait (null = semua warga)
     * ketika pengumuman berstatus terbit dan waktu terbitnya sudah tiba.
     */
    private function notifyResidents(Announcement $item): void
    {
        if ($item->status !== 'published') {
            return;
        }

        if ($item->published_at && $item->published_at->isFuture()) {
            return;
        }

        User::residentUsersOfEstate($item->housing_estate_id)
            ->get()
            ->each(fn (User $residentUser) => $residentUser->notify(
                new NewAnnouncement($item, auth()->user()?->name ?? 'Pengelola'),
            ));
    }

    #[Layout('layouts.admin', ['title' => 'Pengumuman'])]
    public function render()
    {
        return view('livewire.admin.info.announcements', [
            'items' => Announcement::query()
                ->when($this->search, fn ($q) => $q->where(fn ($qq) => $qq
                    ->where('title', 'like', "%{$this->search}%")
                    ->orWhere('content', 'like', "%{$this->search}%")))
                ->orderByRaw("case status when 'published' then 0 when 'draft' then 1 else 2 end")
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(10),
        ]);
    }
}
