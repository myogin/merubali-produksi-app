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
        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->date('production_date');
            $table->string('po_number')->nullable();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('qty_produced');
            $table->string('uom')->default('cartons');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_code']);
            $table->index(['production_date']);
            $table->index(['product_id']);
            $table->index(['po_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_batches');
    }
};
