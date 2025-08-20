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
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('cascade');
            $table->foreignId('packaging_item_id')->constrained('packaging_items')->onDelete('cascade');
            $table->decimal('qty_received', 10, 3);
            $table->string('uom')->default('pcs');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['receipt_id']);
            $table->index(['packaging_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
