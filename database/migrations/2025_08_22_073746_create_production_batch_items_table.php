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
        Schema::create('production_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained('production_batches')->onDelete('cascade');
            $table->string('batch_code')->unique();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('qty_produced');
            $table->string('uom')->default('cartons');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_code']);
            $table->index(['production_batch_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_batch_items');
    }
};
