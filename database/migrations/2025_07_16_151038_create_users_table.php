<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('prs_id')->unique()->nullable();
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles');
            $table->foreignId('main_branch_id')->nullable()->constrained('branches');
            $table->string('password');
            $table->string('signature_image_path')->nullable();
            $table->boolean('status')->default(true);
             // ✅ THÊM MỚI: Cột để lưu ID của người quản lý trực tiếp
            $table->foreignId('manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
