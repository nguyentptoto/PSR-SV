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
        Schema::create('pdf_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pia_code')->unique(); // Mã PR từ người dùng nhập
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->string('original_pdf_path'); // Đường dẫn tới file PDF gốc đã upload
            $table->string('signed_pdf_path')->nullable(); // Đường dẫn tới file PDF đã ký

            $table->string('status')->default('pending_approval'); // pending_approval, completed, rejected
            $table->text('rejection_reason')->nullable(); // THÊM CỘT NÀY VÀO ĐÂY

            $table->integer('current_rank_level')->default(2); // Mặc định cấp duyệt đầu tiên sau khi tạo
            $table->boolean('requires_director_approval')->default(false); // Sửa cú pháp PHP 7.4

            $table->text('remarks')->nullable(); // Ghi chú thêm

            // Các trường để lưu vị trí ký (nếu bạn muốn cấu hình động)
            // Ví dụ: tọa độ X, Y trên trang PDF (tính bằng mm hoặc pt)
            $table->decimal('signature_pos_x', 8, 2)->nullable();
            $table->decimal('signature_pos_y', 8, 2)->nullable();
            $table->decimal('signature_width', 8, 2)->default(50); // Chiều rộng ảnh ký
            $table->decimal('signature_height', 8, 2)->default(20); // Chiều cao ảnh ký
            $table->integer('signature_page')->default(1); // Trang để ký

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_purchase_requests');
    }
};
