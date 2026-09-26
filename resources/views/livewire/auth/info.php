<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perumku - Dokumentasi Aplikasi SaaS Multi-Tenant</title>
  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
      --bg-main: #f8fafc;
      --text-main: #0f172a;
      --text-muted: #475569;
      --border-color: #e2e8f0;
      --code-bg: #0f172a;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      line-height: 1.6;
      color: var(--text-main);
      background-color: var(--bg-main);
      padding: 2rem 1rem;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
      background: #ffffff;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .header-banner {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #ffffff;
      padding: 2rem;
      border-radius: 8px;
      margin-bottom: 2rem;
      border-left: 6px solid var(--primary);
    }

    .header-title {
      font-size: 2.25rem;
      font-weight: 800;
      letter-spacing: -0.025em;
      margin-bottom: 0.5rem;
    }

    .header-subtitle {
      font-size: 1rem;
      color: #94a3b8;
      margin-bottom: 1rem;
    }

    .badges {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .badge {
      background-color: #334155;
      color: #38bdf8;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.25rem 0.625rem;
      border-radius: 4px;
    }

    h2 {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1e3a8a;
      margin-top: 2rem;
      margin-bottom: 1rem;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--border-color);
    }

    h3 {
      font-size: 1.125rem;
      font-weight: 600;
      margin-top: 1.5rem;
      margin-bottom: 0.5rem;
    }

    p {
      margin-bottom: 1rem;
      color: var(--text-main);
    }

    a {
      color: var(--primary);
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    ul, ol {
      margin-bottom: 1rem;
      padding-left: 1.5rem;
    }

    li {
      margin-bottom: 0.25rem;
    }

    code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.875em;
      background-color: #f1f5f9;
      color: #0f172a;
      padding: 0.2em 0.4em;
      border-radius: 4px;
    }

    pre {
      background-color: var(--code-bg);
      color: #f8fafc;
      padding: 1rem;
      border-radius: 8px;
      overflow-x: auto;
      margin-bottom: 1rem;
    }

    pre code {
      background-color: transparent;
      color: inherit;
      padding: 0;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.5rem;
      font-size: 0.9rem;
    }

    th, td {
      padding: 0.75rem 1rem;
      text-align: left;
      border: 1px solid var(--border-color);
    }

    th {
      background-color: #1e293b;
      color: #ffffff;
      font-weight: 600;
    }

    tr:nth-child(even) td {
      background-color: #f8fafc;
    }

    .callout {
      background-color: #eff6ff;
      border-left: 4px solid var(--primary);
      padding: 1rem;
      border-radius: 0 8px 8px 0;
      margin-bottom: 1rem;
    }

    .callout-warning {
      background-color: #fffbeb;
      border-left-color: #f59e0b;
    }

    .callout-title {
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .callout-warning .callout-title {
      color: #b45309;
    }

    .toc-container {
      background-color: #f8fafc;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
    }

    .toc-title {
      font-weight: 700;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 0.05em;
      color: var(--text-muted);
    }

    .toc-list {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 0.5rem;
      list-style: none;
      padding-left: 0;
    }
  </style>
</head>
<body>

<div class="container">

  <div class="header-banner">
    <div class="header-title">Perumku</div>
    <div class="header-subtitle">
      Aplikasi Manajemen Perumahan (SaaS Multi-Tenant) untuk Pengelolaan Hunian, Keuangan IPL, Meter Air, dan Layanan Warga.
    </div>
    <div class="badges">
      <span class="badge">Laravel 13</span>
      <span class="badge">Livewire 4</span>
      <span class="badge">Tailwind CSS 4</span>
      <span class="badge">PHP 8.3+</span>
      <span class="badge">Multi-Tenant</span>
    </div>
  </div>

  <p>
    Aplikasi manajemen perumahan (Perumku) yang dirancang untuk dijalankan sebagai <strong>SaaS multi-tenant</strong>: satu instalasi melayani banyak perumahan, dan data satu perumahan tidak pernah bisa dilihat — maupun ditulis — oleh pengelola atau warga perumahan lain.
  </p>
  <p>
    Dibangun dengan <strong>Laravel 13 + Livewire 4</strong>, tanpa framework JavaScript di sisi klien.
  </p>

  <div class="toc-container">
    <div class="toc-title">Daftar Isi</div>
    <ul class="toc-list">
      <li><a href="#fitur">1. Fitur</a></li>
      <li><a href="#stack">2. Stack</a></li>
      <li><a href="#kebutuhan-sistem">3. Kebutuhan Sistem</a></li>
      <li><a href="#instalasi">4. Instalasi</a></li>
      <li><a href="#akun-demo">5. Akun Demo</a></li>
      <li><a href="#arsitektur-multi-tenant">6. Arsitektur Multi-Tenant</a></li>
      <li><a href="#perintah-sehari-hari">7. Perintah Sehari-hari</a></li>
      <li><a href="#pengujian">8. Pengujian</a></li>
      <li><a href="#struktur-folder">9. Struktur Folder</a></li>
      <li><a href="#yang-belum-tersedia">10. Yang Belum Tersedia</a></li>
    </ul>
  </div>

  <h2 id="fitur">Fitur</h2>
  <ul>
    <li><strong>Data Master:</strong> Perumahan, blok, rumah, warga, serta relasi warga dan rumah (termasuk status aktif dan penanda rumah utama).</li>
    <li><strong>Keuangan — IPL:</strong> Tarif IPL per condominan, generate tagihan bulanan, daftar tagihan, verifikasi pembayaran, upload bukti bayar, dan pencatatan kas.</li>
    <li><strong>Keuangan — Air:</strong> Tarif air per m³, input bacaan meter (dengan foto), dan generate tagihan air otomatis dari selisih pemakaian.</li>
    <li><strong>Keuangan — Kas Warga:</strong> Akun kas, dan transaksi masuk atau keluar yang otomatis terikat ke pembayaran yang sudah diverifikasi.</li>
    <li><strong>Info dan Layanan:</strong> Pengumuman, serta pengaduan warga dengan balasan dan riwayat status.</li>
    <li><strong>Interaksi:</strong> Forum warga (dengan komentar), dan permainan catur antar-warga.</li>
    <li><strong>Sistem:</strong> Manajemen pengguna dan peran, log aktivitas, serta notifikasi.</li>
    <li><strong>Sisi warga:</strong> Dashboard, riwayat dan pembayaran tagihan, upload bukti, pengaduan, pengumuman, forum, profil, dan catur.</li>
  </ul>

  <h2 id="stack">Stack</h2>
  <table>
    <thead>
      <tr>
        <th>Komponen</th>
        <th>Versi</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>PHP</td><td><code>^8.3</code></td></tr>
      <tr><td>Laravel</td><td><code>^13.17</code></td></tr>
      <tr><td>Livewire</td><td><code>^4.4</code></td></tr>
      <tr><td>Tailwind CSS</td><td><code>^4.0</code></td></tr>
      <tr><td>Vite</td><td><code>^8.0</code></td></tr>
      <tr><td>PHPUnit</td><td><code>^12.5</code></td></tr>
      <tr><td>Pint</td><td><code>^1.27</code></td></tr>
      <tr><td>Laravel Boost</td><td><code>^2.10</code></td></tr>
    </tbody>
  </table>
  <p>
    Ikon memakai <a href="https://lucide.dev" target="_blank">Lucide</a>. Autentikasi, sesi, dan RBAC (peran serta permission) dibuat sendiri — tidak memakai paket autentikasi bawaan framework.
  </p>
  <p><strong>Database:</strong> MySQL atau MariaDB. Skema dibuat lewat 39 migrasi.</p>

  <h2 id="kebutuhan-sistem">Kebutuhan Sistem</h2>
  <ul>
    <li>PHP 8.3 atau lebih baru, dengan ekstensi <code>pdo_mysql</code></li>
    <li>Composer 2</li>
    <li>Node.js 20 atau lebih baru, dan npm</li>
    <li>MySQL 8 (atau MariaDB yang setara)</li>
  </ul>

  <h2 id="instalasi">Instalasi</h2>
  <p><strong>1. Dependensi, konfigurasi dasar, dan build aset</strong></p>
  <pre><code># 1. Dependensi, konfigurasi dasar, dan build aset
composer run setup</code></pre>
  <p>Script <code>setup</code> menjalankan <code>composer install</code>, menyalin <code>.env.example</code> ke <code>.env</code>, membuat app key, memigrasi, lalu <code>npm install</code> dan <code>npm run build</code>.</p>

  <p><strong>2. Atur koneksi database.</strong> Buka <code>.env</code> dan sesuaikan:</p>
  <pre><code>APP_NAME="Perumku"
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=perumku_saas
DB_USERNAME=root
DB_PASSWORD=</code></pre>

  <p>Buat database lebih dulu bila belum ada:</p>
  <pre><code>CREATE DATABASE perumku_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code></pre>

  <div class="callout callout-warning">
    <div class="callout-title">Penting</div>
    Aplikasi memakai <code>MEDIUMBLOB</code> untuk foto meter dan bukti pembayaran. Pastikan <code>max_allowed_packet</code> MySQL cukup (default 64 MB biasanya sudah cukup).
  </div>

  <p><strong>3. Migrasi dan data contoh:</strong></p>
  <pre><code>php artisan migrate
php artisan db:seed</code></pre>
  <p>Urutan seeder: <code>RolePermissionSeeder</code>, <code>HousingSeeder</code>, <code>IplSeeder</code>, lalu <code>AnnouncementSeeder</code>.</p>

  <p><strong>4. Jalankan:</strong></p>
  <pre><code>composer run dev</code></pre>
  <p>Atau jalankan Vite dan PHP terpisah bila ingin hot reload:</p>
  <pre><code>npm run dev
php artisan serve</code></pre>

  <h2 id="akun-demo">Akun Demo</h2>
  <p>Semua akun contoh memakai kata sandi <code>password123</code>, dan dibuat oleh <code>db:seed</code>.</p>

  <table>
    <thead>
      <tr>
        <th>Peran</th>
        <th>Email</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Super Admin (platform)</td><td><code>admin@housinghub.id</code></td></tr>
      <tr><td>Admin condominan</td><td><code>info@housinghub.id</code></td></tr>
      <tr><td>Warga</td><td><code>warga.a.1@housinghub.id</code></td></tr>
    </tbody>
  </table>

  <p>
    Pola email warga adalah <code>warga.{blok}.{urutan}@housinghub.id</code>. Data contoh mencakup blok <strong>A, B, C</strong> dengan 4 rumah per blok, sehingga tersedia <code>warga.a.1</code> sampai <code>warga.c.4</code>.
  </p>

  <div class="callout">
    <div class="callout-title">Catatan Peran</div>
    <code>admin@housinghub.id</code> adalah <strong>super admin platform</strong>, bukan admin condominan biasa. Akun ini melihat seluruh database. Untuk mencoba isolasi antar-hook, daftar condominan baru lewat halaman Daftar (<code>/register</code>), lalu bandingkan dengan akun admin yang terikat condominan tersebut.
  </div>

  <h3>Peran yang tersedia</h3>
  <table>
    <thead>
      <tr>
        <th>Slug</th>
        <th>Nama</th>
        <th>Fokus</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>super_admin</code></td><td>Super Admin</td><td>Akses penuh seluruh platform</td></tr>
      <tr><td><code>admin</code></td><td>Admin</td><td>Operasional condominan</td></tr>
      <tr><td><code>finance</code></td><td>Finance</td><td>Keuangan dan IPL</td></tr>
      <tr><td><code>rt</code></td><td>RT</td><td>Data warga dan administrasi</td></tr>
      <tr><td><code>rw</code></td><td>RW</td><td>Monitoring wilayah</td></tr>
      <tr><td><code>security</code></td><td>Security</td><td>Tamu, kendaraan, paket</td></tr>
      <tr><td><code>maintenance</code></td><td>Maintenance</td><td>Pengaduan dan maintenance</td></tr>
      <tr><td><code>resident</code></td><td>Resident</td><td>Warga</td></tr>
    </tbody>
  </table>
  <p>Hak akses per peran dikelola lewat tabel <code>permissions</code> dan <code>role_permissions</code>, lalu dipakai middleware <code>permission:</code>.</p>

  <h2 id="arsitektur-multi-tenant">Arsitektur Multi-Tenant</h2>
  <p>Bagian ini menjelaskan bagaimana data antar-hook tidak bisa bercampur. Ini penting untuk dipahami sebelum menambah fitur baru.</p>

  <h3>1. Konteks tenant</h3>
  <p>Setiap akun terikat ke satu condominan lewat kolom <code>users.housing_estate_id</code>.</p>
  <p><code>app/Support/CurrentEstate.php</code> menentukan condominan aktif untuk request sekarang, dengan urutan:</p>

  <table>
    <thead>
      <tr>
        <th>Kondisi pengguna</th>
        <th><code>CurrentEstate::id()</code></th>
        <th>Arti</th>
      </tr>
    </thead>
    <tbody>
      <tr><td><code>super_admin</code></td><td><code>null</code></td><td>Tanpa filter, melihat semua</td></tr>
      <tr><td>Staf dengan <code>housing_estate_id</code></td><td><code>int</code></td><td>Terbatas condominan tersebut, plus baris global</td></tr>
      <tr><td>Warga dengan rumah hunian aktif</td><td><code>int</code></td><td>Sama seperti di atas</td></tr>
      <tr><td>Warga tanpa rumah atau staf tanpa hook</td><td><code>NONE (-1)</code></td><td>Hanya baris global</td></tr>
    </tbody>
  </table>

  <p>Resolusi dilakukan <strong>secara lazy dari <code>auth()->user()</code></strong>, bukan lewat middleware. Alasannya: middleware hanya berjalan pada request HTTP baru, sedangkan Livewire memproses aksi lewat endpoint <code>POST /livewire/update</code>. Dengan cara ini, scope yang sama berlaku di kedua kasus, termasuk saat <code>Livewire::test()</code>.</p>

  <h3>2. Scope global (sisi baca)</h3>
  <p><code>app/Models/Scopes/BelongsToEstateScope.php</code> disisipkan ke <strong>20 model</strong> melalui trait <code>App\Models\Concerns\BelongsToEstate</code>. Setiap query Eloquent otomatis mendapat <code>WHERE housing_estate_id = ...</code>, sehingga lupa menambahkan filter di sebuah komponen tidak menyebabkan kebocoran data.</p>
  <p>Model yang memakai trait:</p>
  <pre><code>ActivityLog, Announcement, Billing, CashAccount, CashTransaction, ChessGame,
Complaint, ComplaintResponse, House, HouseResident, HousingBlock, HousingEstate,
IplRate, Payment, Post, PostComment, Resident, User, WaterMeterReading, WaterRate</code></pre>

  <h3>3. Kunci tulis (sisi tulis)</h3>
  <p>Trait yang sama memasang hook <code>saving</code> (<code>applyEstateWriteScope()</code>) yang memaksa <code>housing_estate_id</code> ke condominan aktif, apa pun nilai yang dikirim formulir. Jadi memalsukan <code>housing_estate_id</code> di POST tidak memberi akses ke condominan lain.</p>
  <p>Hook ini sengaja hanya berjalan saat baris baru dibuat atau saat kolom estate benar-benar berubah, sehingga operasi biasa seperti pembaruan <code>last_login_at</code> saat login tidak ikut terkunci.</p>

  <h3>4. Pengekalan pada tabel utama</h3>
  <p>Awalnya <code>billings</code> dan <code>payments</code> tidak punya <code>housing_estate_id</code>, dan terisolasi lewat relasi berlapis (<code>payment</code> ke <code>billing</code> ke <code>house</code>). Itu berarti setiap query berjalan memakai subquery korelasi. Kedua tabel kini punya kolom sendiri yang di-denormalisasi dari relasi tersebut, dilengkapi index:</p>
  <pre><code>billings_estate_status_index   (housing_estate_id, status)
billings_estate_period_index   (housing_estate_id, period_year, period_month)
payments_estate_status_index   (housing_estate_id, status)</code></pre>
  <p>Model <code>Billing</code> dan <code>Payment</code> juga menurunkan nilainya sendiri pada event <code>creating</code> bila pemanggil tidak menyediakannya, sehingga tidak mungkin ada tagihan tanpa condominan yang lalu hilang dari daftar tagihan semua admin.</p>
  <div class="callout">
    Catatan: kolom <code>housing_estate_id</code> pada kedua tabel memakai <code>cascadeOnDelete</code>, yaitu menghapus condominan akan ikut menghapus seluruh tagihan dan pembayarannya. Ini konsisten dengan <code>house_id</code>. Bila penghapusan condominan seharusnya ditolak selama masih ada transaksi, ubah ke <code>restrictOnDelete</code>.
  </div>

  <h3>5. Tampilan di antarmuka</h3>
  <p><code>resources/views/components/ui/estate-field.blade.php</code> menampilkan dropdown Perumahan hanya bila ada lebih dari satu condominan yang boleh dipilih. Admin condominan melihat label statis, karena memang sudah terikat satu.</p>

  <h3>6. Batasan yang perlu diketahui</h3>
  <div class="callout callout-warning">
    <strong>Global scope tidak berlaku pada query builder mentah.</strong> <code>DB::table('houses')</code> atau <code>DB::select(...)</code> sama sekali tidak melewati Eloquent, sehingga tidak ter-scope. Saat menambah fitur, gunakan Eloquent, atau panggil <code>withoutGlobalScope(BelongsToEstateScope::class)</code> secara eksplisit dan sadar.<br><br>
    Semua pemakaian query mentah yang sudah ada terbukti aman, karena selalu memfilter <code>resident_id</code> milik pengguna sendiri.
  </div>

  <h2 id="perintah-sehari-hari">Perintah Sehari-hari</h2>
  <pre><code># Server pengembangan (PHP, log, queue, Vite)
composer run dev

# Build aset untuk produksi
npm run build

# Buka tinker
php artisan tinker

# Reseed dari nol
php artisan migrate:fresh --seed

# Format kode
vendor/bin/pint
vendor/bin/pint --test      # hanya memeriksa, tidak menulis</code></pre>

  <h2 id="pengujian">Pengujian</h2>
  <pre><code>php artisan test
php artisan test --filter=TenantIsolationTest</code></pre>

  <p><strong>Konfigurasi test.</strong> <code>phpunit.xml</code> memakai MySQL dengan database tetap <code>housingdb_test</code>:</p>
  <pre><code>DB_CONNECTION=mysql
DB_DATABASE=housingdb_test</code></pre>

  <div class="callout callout-warning">
    <strong>Jalankan test secara berurutan, bukan paralel.</strong> Karena database test-nya sama, beberapa proses yang berjalan bersamaan akan saling menabrak tabel dan menghasilkan kegagalan palsu (<code>Table 'sessions' already exists</code>, <code>Table 'migrations' doesn't exist</code>). Bila CI menjalankan test paralel, database per-proses akan menyelesaikan masalahnya.
  </div>

  <h3>Test isolasi tenant</h3>
  <p><code>tests/Feature/TenantIsolationTest.php</code> berisi 17 test yang membuktikan data antar-hook tidak bocor:</p>
  <ul>
    <li>Admin dan warga hook A tidak melihat data hook B</li>
    <li>Akses langsung lewat URL ke tagihan, pengaduan, postingan, atau bukti bayar hook lain menghasilkan <strong>404</strong></li>
    <li>Warga lain di hook yang sama tetap <strong>403</strong>, bukan 404 yang membocorkan keberadaan</li>
    <li>Formulir Livewire yang <code>housing_estate_id</code>-nya dipalsukan dipaksa ke hook sendiri</li>
    <li>Pengumuman, forum, dan tagihan air dipisahkan per hook</li>
    <li>Notifikasi staf tidak dikirim ke staf hook lain</li>
    <li><code>super_admin</code> tetap melihat seluruh data</li>
    <li>Dropdown condominan hanya menawarkan hook sendiri</li>
    <li>Halaman admin tidak menampilkan dropdown condominan yang tak berguna</li>
  </ul>
  <p>Status saat ini: <strong>99 test lulus, 438 assertion</strong>.</p>

  <h2 id="struktur-folder">Struktur Folder</h2>
  <pre><code>app/
  Livewire/
    Admin/          Komponen halaman pengelola
    Resident/       Komponen halaman warga
    Auth/           Login dan pendaftaran hook
  Models/
    Concerns/       Trait BelongsToEstate
    Scopes/         BelongsToEstateScope
  Policies/         Otorisasi per model
  Services/         IplBillingService, WaterBillingService
  Support/
    CurrentEstate   Konteks tenant aktif
    AdminMenu       Definisi menu navigasi
resources/views/
  components/ui/    Komponen UI, termasuk estate-field
  livewire/         View tiap komponen
database/
  migrations/       39 migrasi
  seeders/          Data contoh dan hak akses
  factories/        UserFactory
tests/
  Feature/          Test integrasi, termasuk TenantIsolationTest
  Unit/             Test unit</code></pre>

  <h2 id="yang-belum-tersedia">Yang Belum Tersedia</h2>
  <p>Agar tidak ada kejutan, berikut yang belum ada di versi ini:</p>
  <ul>
    <li><strong>Fitur komersial SaaS</strong> — belum ada plan atau langganan, penagihan platform, payment gateway, maupun subdomain per tenant. Isolasi data sudah siap, tetapi monetisasi belum dibangun.</li>
    <li><strong>Lupa sandi</strong> dan verifikasi alamat email.</li>
    <li><strong>Database per tenant</strong> — arsitekturnya satu database dengan baris terpisah per hook, bukan satu database per condominan.</li>
    <li><strong>Line ending konsisten</strong> — belum ada <code>.gitattributes</code>, sehingga Git dapat menampilkan peringatan konversi CRLF dan LF di Windows.</li>
  </ul>

  <h2 id="lisensi">Lisensi</h2>
  <p>MIT.</p>

</div>

</body>
</html>
