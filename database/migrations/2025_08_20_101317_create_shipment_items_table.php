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
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->onDelete('cascade');
            $table->foreignId('production_batch_id')->constrained('production_batches')->onDelete('cascade');
            $table->integer('qty_shipped');
            $table->string('uom')->default('cartons');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shipment_id']);
            $table->index(['production_batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
