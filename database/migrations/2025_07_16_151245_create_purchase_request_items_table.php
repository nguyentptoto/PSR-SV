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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->onDelete('cascade');
            $table->string('item_code');
            $table->string('item_name');
            $table->string('old_item_code')->nullable();
            $table->decimal('order_quantity', 15, 3);
            $table->string('order_unit', 20);
            $table->decimal('inventory_quantity', 15, 3)->nullable();
            $table->string('inventory_unit', 20)->nullable();
            $table->decimal('r3_price', 15, 2)->nullable();
            $table->decimal('estimated_price', 15, 2);
            $table->decimal('subtotal', 20, 2);
            $table->string('using_dept_code')->nullable();
            $table->string('plant_system')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
