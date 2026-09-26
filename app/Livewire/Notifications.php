<?php

namespace App\Livewire;

use App\Notifications\ComplaintUpdated;
use App\Notifications\NewAnnouncement;
use App\Notifications\NewCommentOnPost;
use App\Notifications\NewComplaint;
use App\Notifications\NewForumPost;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

/**
 * Lonceng notifikasi in-app: daftar terbaru, badge unread, tandai dibaca,
 * dan berpindah ke halaman tujuan notifikasi.
 */
class Notifications extends Component
{
    /**
     * Kategori notifikasi yang ditampilkan sebagai tab filter.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'forum' => 'Forum',
        'laporan' => 'Laporan',
        'pengumuman' => 'Pengumuman',
    ];

    /**
     * Pemetaan tipe notifikasi → kategori.
     *
     * @var array<class-string, string>
     */
    public const TYPE_CATEGORY = [
        NewForumPost::class => 'forum',
        NewCommentOnPost::class => 'forum',
        NewComplaint::class => 'laporan',
        ComplaintUpdated::class => 'laporan',
        NewAnnouncement::class => 'pengumuman',
    ];

    public string $variant = 'resident';

    public bool $open = false;

    public string $filter = 'all';

    public function mount(string $variant = 'resident'): void
    {
        $this->variant = $variant;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * Ganti tab filter kategori (all|forum|laporan|pengumuman).
     */
    public function setFilter(string $category): void
    {
        $this->filter = $category === 'all' || array_key_exists($category, self::CATEGORIES)
            ? $category
            : 'all';
    }

    /**
     * Kategori dari sebuah tipe notifikasi.
     */
    public function categoryOf(string $type): string
    {
        return self::TYPE_CATEGORY[$type] ?? 'lainnya';
    }

    /**
     * Tandai satu notifikasi dibaca lalu buka halaman tujuannya.
     */
    public function openItem(string $id): void
    {
        $notification = auth()->user()
            ->notifications()
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return;
        }

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        $this->open = false;
        $this->redirect($this->urlFor($notification), navigate: true);
    }

    /**
     * Tandai seluruh notifikasi sebagai sudah dibaca.
     */
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->each->markAsRead();
    }

    /**
     * Waktu relatif berbahasa Indonesia (tanpa file translation, locale app 'id').
     */
    public function timeAgo(DatabaseNotification $notification): string
    {
        $seconds = max(0, now()->timestamp - $notification->created_at->timestamp);

        return match (true) {
            $seconds < 60 => 'baru saja',
            $seconds < 3600 => ((int) floor($seconds / 60)).' menit lalu',
            $seconds < 86400 => ((int) floor($seconds / 3600)).' jam lalu',
            $seconds < 604800 => ((int) floor($seconds / 86400)).' hari lalu',
            default => $notification->created_at->format('d M Y'),
        };
    }

    /**
     * Halaman tujuan notifikasi sesuai hak akses pengguna.
     */
    public function urlFor(DatabaseNotification $notification): string
    {
        $data = $notification->data;

        return match ($notification->type) {
            // Halaman detail forum hanya ada di sisi resident, tapi policy view
            // terbuka untuk semua user login (termasuk admin/moderator).
            NewForumPost::class, NewCommentOnPost::class => route('resident.forum.show', $data['post_id']),
            NewComplaint::class, ComplaintUpdated::class => auth()->user()->hasPermission('manage-complaint')
                ? route('admin.info.complaints.show', $data['complaint_id'])
                : route('resident.complaints.show', $data['complaint_id']),
            NewAnnouncement::class => auth()->user()->hasPermission('manage-announcement')
                ? route('admin.info.announcements')
                : route('resident.info.index'),
            default => '/',
        };
    }

    public function render()
    {
        $user = auth()->user();

        // Hitung unread per kategori untuk badge pada tab filter.
        $unreadPerCategory = ['forum' => 0, 'laporan' => 0, 'pengumuman' => 0, 'lainnya' => 0];
        foreach ($user->unreadNotifications()->limit(300)->get() as $unread) {
            $unreadPerCategory[$this->categoryOf($unread->type)]++;
        }

        return view('livewire.notifications', [
            'notifications' => $user->notifications()
                ->latest()
                ->limit(60)
                ->get()
                ->filter(fn (DatabaseNotification $n) => $this->filter === 'all'
                    || $this->categoryOf($n->type) === $this->filter)
                ->take(15)
                ->values(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'unreadPerCategory' => $unreadPerCategory,
            'categories' => self::CATEGORIES,
        ]);
    }
}
