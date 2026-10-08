<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\PageView;
use PHPUnit\Framework\TestCase;

class PageViewUsageTest extends TestCase
{
    public function test_device_label_groups_common_user_agents(): void
    {
        $this->assertSame('Ponsel', (new PageView(['user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)']))->deviceLabel());
        $this->assertSame('Ponsel', (new PageView(['user_agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36']))->deviceLabel());
        $this->assertSame('Tablet', (new PageView(['user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)']))->deviceLabel());
        $this->assertSame('Tablet', (new PageView(['user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']))->deviceLabel());
        $this->assertSame('Desktop', (new PageView(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)']))->deviceLabel());
        $this->assertSame('Desktop', (new PageView(['user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)']))->deviceLabel());
    }

    public function test_device_label_handles_missing_user_agent(): void
    {
        $this->assertSame('Tidak diketahui', (new PageView(['user_agent' => null]))->deviceLabel());
        $this->assertSame('Tidak diketahui', (new PageView(['user_agent' => '']))->deviceLabel());
    }

    public function test_module_label_maps_known_slugs_and_falls_back(): void
    {
        $this->assertSame('Forum Warga', ActivityLog::moduleLabel('forum'));
        $this->assertSame('Transaksi Kas', ActivityLog::moduleLabel('cash_transactions'));

        // Modul baru tidak boleh tampil kosong.
        $this->assertSame('modul_baru', ActivityLog::moduleLabel('modul_baru'));
        $this->assertSame('Tanpa Modul', ActivityLog::moduleLabel(null));
    }
}
