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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date');
            $table->enum('item_type', ['packaging', 'finished_goods']);
            $table->unsignedBigInteger('item_id'); // packaging_item_id or product_id
            $table->unsignedBigInteger('batch_id')->nullable(); // production_batch_id for finished goods
            $table->integer('qty');
            $table->string('uom');
            $table->enum('movement_type', ['in', 'out']);
            $table->string('reference_type'); // 'receipt', 'production', 'shipment'
            $table->unsignedBigInteger('reference_id'); // receipt_id, production_batch_id, or shipment_id
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['movement_date']);
            $table->index(['item_type', 'item_id']);
            $table->index(['batch_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
