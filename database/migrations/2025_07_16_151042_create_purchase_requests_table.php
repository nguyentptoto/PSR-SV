<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('pia_code')->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('executing_department_id')->constrained('executing_departments');
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('sap_release_date')->nullable();
            $table->date('requested_delivery_date');
            $table->string('currency', 10);

            $table->decimal('total_amount', 20, 2)->default(0);
            $table->decimal('total_order_quantity', 15, 3)->default(0);
            $table->decimal('total_inventory_quantity', 15, 3)->default(0);

            $table->string('status')->default('pending_approval');
            $table->string('priority')->nullable()->comment('urgent, normal, quotation_only');
            $table->text('remarks')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->integer('current_rank_level')->default(1);
            $table->boolean('requires_director_approval')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
