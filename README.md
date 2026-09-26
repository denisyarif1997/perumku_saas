# Perumku

Aplikasi manajemen perumahan (Perumku) yang dirancang untuk dijalankan sebagai
**SaaS multi-tenant**: satu instalasi melayani banyak perumahan, dan data satu
perumahan tidak pernah bisa dilihat — maupun ditulis — oleh pengelola atau warga
perumahan lain.

Dibangun dengan Laravel 13 + Livewire 4, tanpa framework JavaScript di sisi klien.

---

## Daftar Isi

- [Fitur](#fitur)
- [Stack](#stack)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi](#instalasi)
- [Akun Demo](#akun-demo)
- [Arsitektur Multi-Tenant](#arsitektur-multi-tenant)
- [Perintah Sehari-hari](#perintah-sehari-hari)
- [Pengujian](#pengujian)
- [Struktur Folder](#struktur-folder)
- [Yang Belum Tersedia](#yang-belum-tersedia)

---

## Fitur

**Data Master** — perumahan, blok, rumah, warga, serta relasi warga dan rumah
(termasuk status aktif dan penanda rumah utama).

**Keuangan — IPL** — tarif IPL per condominan, generate tagihan bulanan,
daftar tagihan, verifikasi pembayaran, upload bukti bayar, dan pencatatan kas.

**Keuangan — Air** — tarif air per m3, input bacaan meter (dengan foto), dan
generate tagihan air otomatis dari selisih pemakaian.

**Keuangan — Kas Warga** — akun kas, dan transaksi masuk atau keluar yang
otomatis terait ke pembayaran yang sudah diverifikasi.

**Info dan Layanan** — pengumuman, serta pengaduan warga dengan balasan dan
riwayat status.

**Interaksi** — forum warga (dengan komentar), dan permainan catur antar-warga.

**Sistem** — manajemen pengguna dan peran, log aktivitas, serta notifikasi.

**Sisi warga** — dashboard, riwayat dan pembayaran tagihan, upload bukti,
pengaduan, pengumuman, forum, profil, dan catur.

---

## Stack

| Komponen | Versi |
|---|---|
| PHP | ^8.3 |
| Laravel | ^13.17 |
| Livewire | ^4.4 |
| Tailwind CSS | ^4.0 |
| Vite | ^8.0 |
| PHPUnit | ^12.5 |
| Pint | ^1.27 |
| Laravel Boost | ^2.10 |

Ikon memakai [Lucide](https://lucide.dev). Autentikasi, sesi, dan RBAC
(peran serta permission) dibuat sendiri — tidak memakai paket autentikasi bawaan
framework.

**Database:** MySQL atau MariaDB. Skema dibuat lewat 39 migrasi.

---

## Kebutuhan Sistem

- PHP 8.3 atau lebih baru, dengan ekstensi `pdo_mysql`
- Composer 2
- Node.js 20 atau lebih baru, dan npm
- MySQL 8 (atau MariaDB yang setara)

---

## Instalasi

```bash
# 1. Dependensi, konfigurasi dasar, dan build aset
composer run setup
```

Script `setup` menjalankan `composer install`, menyalin `.env.example` ke `.env`,
membuat app key, memigrasi, lalu `npm install` dan `npm run build`.

**2. Atur koneksi database.** Buka `.env` dan sesuaikan:

```dotenv
APP_NAME="Perumku"
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=perumku_saas
DB_USERNAME=root
DB_PASSWORD=
```

Buat database lebih dulu bila belum ada:

```sql
CREATE DATABASE perumku_saas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> **Penting:** aplikasi memakai `MEDIUMBLOB` untuk foto meter dan bukti
> pembayaran. Pastikan `max_allowed_packet` MySQL cukup (default 64 MB
> biasanya sudah cukup).

**3. Migrasi dan data contoh:**

```bash
php artisan migrate
php artisan db:seed
```

Urutan seeder: `RolePermissionSeeder`, `HousingSeeder`, `IplSeeder`, lalu
`AnnouncementSeeder`.

**4. Jalankan:**

```bash
composer run dev
```

Atau jalankan Vite dan PHP terpisah bila ingin hot reload:

```bash
npm run dev
php artisan serve
```

---

## Akun Demo

Semua akun contoh memakai kata sandi **`password123`**, dan dibuat oleh `db:seed`.

| Peran | Email |
|---|---|
| Super Admin (platform) | `admin@housinghub.id` |
| Admin condominan | `info@housinghub.id` |
| Warga | `warga.a.1@housinghub.id` |

Pola email warga adalah `warga.{blok}.{urutan}@housinghub.id`. Data contoh
mencakup blok **A, B, C** dengan 4 rumah per blok, sehingga tersedia
`warga.a.1` sampai `warga.c.4`.

> **Penting:** `admin@housinghub.id` adalah **super admin platform**, bukan admin
> condominan biasa. Akun ini melihat seluruh database. Untuk mencoba isolasi
> antar-hook, daftar condominan baru lewat halaman Daftar (`/register`), lalu
> bandingkan dengan akun admin yang terikat condominan tersebut.

### Peran yang tersedia

| Slug | Nama | Fokus |
|---|---|---|
| `super_admin` | Super Admin | Akses penuh seluruh platform |
| `admin` | Admin | Operasional condominan |
| `finance` | Finance | Keuangan dan IPL |
| `rt` | RT | Data warga dan administrasi |
| `rw` | RW | Monitoring wilayah |
| `security` | Security | Tamu, kendaraan, paket |
| `maintenance` | Maintenance | Pengaduan dan maintenance |
| `resident` | Resident | Warga |

Hak akses per peran dikelola lewat tabel `permissions` dan `role_permissions`,
lalu dipakai middleware `permission:`.

---

## Arsitektur Multi-Tenant

Bagian ini menjelaskan bagaimana data antar-hook tidak bisa bercampur. Ini
penting untuk dipahami sebelum menambah fitur baru.

### 1. Konteks tenant

Setiap akun terikat ke satu condominan lewat kolom **`users.housing_estate_id`**.

`app/Support/CurrentEstate.php` menentukan condominan aktif untuk request
sekarang, dengan urutan:

| Kondisi pengguna | `CurrentEstate::id()` | Arti |
|---|---|---|
| `super_admin` | `null` | Tanpa filter, melihat semua |
| Staf dengan `housing_estate_id` | `int` | Terbatas condominan tersebut, plus baris global |
| Warga dengan rumah hunian aktif | `int` | Sama seperti di atas |
| Warga tanpa rumah atau staf tanpa hook | `NONE` (`-1`) | Hanya baris global |

Resolusi dilakukan **secara lazy dari `auth()->user()`**, bukan lewat middleware.
Alasannya: middleware hanya berjalan pada request HTTP baru, sedangkan Livewire
memproses aksi lewat endpoint `POST /livewire/update`. Dengan cara ini, scope yang
sama berlaku di kedua kasus, termasuk saat `Livewire::test()`.

### 2. Scope global (sisi baca)

`app/Models/Scopes/BelongsToEstateScope.php` disisipkan ke **20 model** melalui
trait `App\Models\Concerns\BelongsToEstate`. Setiap query Eloquent otomatis
mendapat `WHERE housing_estate_id = ...`, sehingga lupa menambahkan filter di
sebuah komponen tidak menyebabkan kebocoran data.

Model yang memakai trait:

```
ActivityLog, Announcement, Billing, CashAccount, CashTransaction, ChessGame,
Complaint, ComplaintResponse, House, HouseResident, HousingBlock, HousingEstate,
IplRate, Payment, Post, PostComment, Resident, User, WaterMeterReading, WaterRate
```

### 3. Kunci tulis (sisi tulis)

Trait yang sama memasang hook `saving` (`applyEstateWriteScope()`) yang memaksa
`housing_estate_id` ke condominan aktif, apa pun nilai yang dikirim formulir.
Jadi memalsukan `housing_estate_id` di POST tidak memberi akses ke condominan lain.

Hook ini sengaja hanya berjalan saat baris baru dibuat atau saat kolom estate
benar-benar berubah, sehingga operasi biasa seperti pembaruan `last_login_at`
saat login tidak ikut terkunci.

### 4. Pengekalan pada tabel utama

Awalnya `billings` dan `payments` tidak punya `housing_estate_id`, dan terisolasi
lewat relasi berlapis (`payment` ke `billing` ke `house`). Itu berarti setiap query
berjalan memakai subquery korelasi. Kedua tabel kini punya kolom sendiri yang
di-denormalisasi dari relasi tersebut, dilengkapi index:

```
billings_estate_status_index   (housing_estate_id, status)
billings_estate_period_index   (housing_estate_id, period_year, period_month)
payments_estate_status_index   (housing_estate_id, status)
```

Model `Billing` dan `Payment` juga menurunkan nilainya sendiri pada event
`creating` bila pemanggil tidak menyediakannya, sehingga tidak mungkin ada tagihan
tanpa condominan yang lalu hilang dari daftar tagihan semua admin.

> Catatan: kolom `housing_estate_id` pada kedua tabel memakai `cascadeOnDelete`,
> yaitu menghapus condominan akan ikut menghapus seluruh tagihan dan
> pembayarannya. Ini konsisten dengan `house_id`. Bila penghapusan condominan
> seharusnya ditolak selama masih ada transaksi, ubah ke `restrictOnDelete`.

### 5. Tampilan di antarmuka

`resources/views/components/ui/estate-field.blade.php` menampilkan dropdown
Perumahan hanya bila ada lebih dari satu condominan yang boleh dipilih. Admin
condominan melihat label statis, karena memang sudah terikat satu.

### 6. Batasan yang perlu diketahui

> **Global scope tidak berlaku pada query builder mentah.** `DB::table('houses')`
> atau `DB::select(...)` sama sekali tidak melewati Eloquent, sehingga tidak
> ter-scope. Saat menambah fitur, gunakan Eloquent, atau panggil
> `withoutGlobalScope(BelongsToEstateScope::class)` secara eksplisit dan sadar.
>
> Semua pemakaian query mentah yang sudah ada terbukti aman, karena selalu
> memfilter `resident_id` milik pengguna sendiri.

---

## Perintah Sehari-hari

```bash
# Server pengembangan (PHP, log, queue, Vite)
composer run dev

# Build aset untuk produksi
npm run build

# Buka tinker
php artisan tinker

# Reseed dari nol
php artisan migrate:fresh --seed

# Format kode
vendor/bin/pint
vendor/bin/pint --test      # hanya memeriksa, tidak menulis
```

---

## Pengujian

```bash
php artisan test
php artisan test --filter=TenantIsolationTest
```

**Konfigurasi test.** `phpunit.xml` memakai MySQL dengan database tetap
`housingdb_test`:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=housingdb_test
```

> **Jalankan test secara berurutan, bukan paralel.** Karena database test-nya
> sama, beberapa proses yang berjalan bersamaan akan saling menabrak tabel dan
> menghasilkan kegagalan palsu (`Table 'sessions' already exists`,
> `Table 'migrations' doesn't exist`). Bila CI menjalankan test paralel, database
> per-proses akan menyelesaikan masalahnya.

### Test isolasi tenant

`tests/Feature/TenantIsolationTest.php` berisi 17 test yang membuktikan data
antar-hook tidak bocor:

- Admin dan warga hook A tidak melihat data hook B
- Akses langsung lewat URL ke tagihan, pengaduan, postingan, atau bukti bayar
  hook lain menghasilkan **404**
- Warga lain di hook yang sama tetap **403**, bukan 404 yang membocorkan keberadaan
- Formulir Livewire yang `housing_estate_id`-nya dipalsukan dipaksa ke hook sendiri
- Pengumuman, forum, dan tagihan air dipisahkan per hook
- Notifikasi staf tidak dikirim ke staf hook lain
- `super_admin` tetap melihat seluruh data
- Dropdown condominan hanya menawarkan hook sendiri
- Halaman admin tidak menampilkan dropdown condominan yang tak berguna

Status saat ini: **99 test lulus, 438 assertion**.

---

## Struktur Folder

```
app/
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
  Unit/             Test unit
```

---

## Yang Belum Tersedia

Agar tidak ada kejutan, berikut yang belum ada di versi ini:

- **Fitur komersial SaaS** — belum ada plan atau langganan, penagihan platform,
  payment gateway, maupun subdomain per tenant. Isolasi data sudah siap, tetapi
  monetisasi belum dibangun.
- **Lupa sandi** dan verifikasi alamat email.
- **Database per tenant** — arsitekturnya satu database dengan baris terpisah
  per hook, bukan satu database per condominan.
- **Line ending konsisten** — belum ada `.gitattributes`, sehingga Git dapat
  menampilkan peringatan konversi CRLF dan LF di Windows.

---

## Lisensi

MIT.
