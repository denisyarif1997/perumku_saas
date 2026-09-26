<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\HousingEstate;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $estate = HousingEstate::first();
        $author = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        $announcements = [
            [
                'title' => 'Jadwal Pemadaman Listrik Terencana',
                'content' => 'Diberitahukan kepada seluruh warga bahwa akan ada pemadaman listrik terencana pada Sabtu, 19 September 2026 pukul 09.00–13.00 WIB untuk perawatan jaringan PLN. Mohon siapkan cadangan daya untuk keperluan mendesak.',
                'category' => 'maintenance',
                'priority' => 'high',
                'is_pinned' => true,
            ],
            [
                'title' => 'IPL Bulan September 2026 Telah Diterbitkan',
                'content' => 'Tagihan IPL periode September 2026 sudah dapat dilihat pada menu IPL di aplikasi. Mohon lakukan pembayaran sebelum tanggal 10 untuk menghindari denda keterlambatan.',
                'category' => 'billing',
                'priority' => 'normal',
                'is_pinned' => false,
            ],
            [
                'title' => 'Kerja Bakti Bulanan',
                'content' => 'Kerja bakti bulanan akan dilaksanakan hari Minggu pagi pukul 07.00. Titik kumpul di pos keamanan. Mohon partisipasi seluruh warga.',
                'category' => 'event',
                'priority' => 'low',
                'is_pinned' => false,
            ],
        ];

        foreach ($announcements as $data) {
            $announcement = Announcement::firstOrCreate(
                ['title' => $data['title']],
                $data + [
                    'housing_estate_id' => $estate?->id,
                    'user_id' => $author?->id,
                    'status' => 'published',
                    'published_at' => now()->subDays(random_int(1, 3)),
                ]
            );

            ActivityLog::firstOrCreate([
                'user_id' => $author?->id,
                'action' => 'create',
                'module' => 'announcements',
                'subject_type' => Announcement::class,
                'subject_id' => $announcement->id,
            ], [
                'description' => 'Seed pengumuman: '.$announcement->title,
            ]);
        }
    }
}
