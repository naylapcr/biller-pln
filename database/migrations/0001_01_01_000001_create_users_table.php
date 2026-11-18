<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master biller (PRD bagian 7). Data ACMT hanya memuat login dan kode petugas, jadi NIP boleh kosong.
        Schema::create('billers', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama', 100);
            $table->string('zona_wilayah', 100)->nullable();
            $table->string('kode_petugas_acmt', 10)->unique();
            $table->string('login_acmt', 60)->unique(); // selalu huruf kecil
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username_nip', 60)->unique();
            $table->string('nama', 100);
            $table->string('password');
            $table->string('role', 20); // super_admin, admin, biller
            $table->foreignId('biller_id')->nullable()->unique()->constrained('billers')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('billers');
    }
};
