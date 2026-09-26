<?php

namespace Tests;

use App\Models\Scopes\BelongsToEstateScope;
use App\Models\User;
use App\Support\CurrentEstate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // CurrentEstate memakai state statis; direset per test agar konteks estate
    // dari test sebelumnya tidak bocor ke test berikutnya.
    protected function setUp(): void
    {
        parent::setUp();

        CurrentEstate::flush();
    }

    /**
     * Muat ulang model tanpa filter estate.
     *
     * Diperlukan saat test beractingAs sebagai user dari estate tertentu lalu
     * perlu memeriksa akun miliknya sendiri (atau akun platform) yang berada
     * di luar estate aktif — kondisi yang memang tidak terlihat dari dalam
     * sesi tersebut.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    protected function withoutEstateScope(Model $model): Model
    {
        return $model->newQueryWithoutScope(BelongsToEstateScope::class)->findOrFail($model->getKey());
    }

    /**
     * Ambil user berdasarkan email tanpa filter estate.
     *
     * Akun platform (housing_estate_id null) memang tidak terlihat dari
     * dalam sesi user estate tertentu, jadi test yang perlu beractingAs
     * dengannya setelah berganti konteks harus lewat helper ini.
     */
    protected function findUserUnscoped(string $email): User
    {
        return User::withoutGlobalScope(BelongsToEstateScope::class)
            ->where('email', $email)
            ->firstOrFail();
    }
}
