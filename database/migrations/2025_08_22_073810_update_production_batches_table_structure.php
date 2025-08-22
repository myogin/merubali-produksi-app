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
        Schema::table('production_batches', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['product_id']);

            // Drop columns that will be moved to production_batch_items
            $table->dropColumn(['batch_code', 'product_id', 'qty_produced', 'uom']);

            // Keep po_number, production_date, and notes at header level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            // Restore the original columns
            $table->string('batch_code')->unique();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('qty_produced');
            $table->string('uom')->default('cartons');

            // Restore indexes
            $table->index(['batch_code']);
            $table->index(['product_id']);
        });
    }
};
