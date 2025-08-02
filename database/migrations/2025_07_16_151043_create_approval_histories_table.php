<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->id();
            // THAY ĐỔI DÒNG NÀY ĐỂ purchase_request_id CHO PHÉP NULL
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->onDelete('cascade');

            // DÒNG NÀY ĐÃ CÓ TRONG CODE CỦA BẠN VÀ ĐÃ ĐÚNG LÀ NULLABLE VÀ SAU purchase_request_id
            $table->foreignId('pdf_purchase_request_id')->nullable()->constrained('pdf_purchase_requests')->onDelete('cascade');

            $table->foreignId('user_id')->constrained('users'); // Dòng này thường nằm ở đây

            $table->string('rank_at_approval');
            $table->string('action'); // created, approved, rejected, updated
            $table->string('signature_image_path')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
    }
};
