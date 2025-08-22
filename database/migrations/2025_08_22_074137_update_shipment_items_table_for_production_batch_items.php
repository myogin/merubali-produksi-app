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
        Schema::table('shipment_items', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['production_batch_id']);

            // Rename the column to reference production_batch_items
            $table->renameColumn('production_batch_id', 'production_batch_item_id');

            // Add new foreign key constraint to production_batch_items
            $table->foreign('production_batch_item_id')->references('id')->on('production_batch_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            // Drop the new foreign key constraint
            $table->dropForeign(['production_batch_item_id']);

            // Rename the column back
            $table->renameColumn('production_batch_item_id', 'production_batch_id');

            // Restore the original foreign key constraint
            $table->foreign('production_batch_id')->references('id')->on('production_batches')->onDelete('cascade');
        });
    }
};
