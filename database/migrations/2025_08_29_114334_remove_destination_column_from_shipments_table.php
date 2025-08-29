<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: This migration should only be run after confirming that the destination_id
     * foreign key is working properly and all existing data has been migrated.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // First, safely drop the index if it exists
            // Use the actual index name that Laravel would have created
            try {
                $table->dropIndex('shipments_destination_index');
            } catch (\Exception $e) {
                // Index might not exist, continue with column removal
            }

            // Remove the old destination string column
            $table->dropColumn('destination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Restore the old destination column
            $table->string('destination')->after('shipment_date');

            // Restore the index
            $table->index(['destination']);
        });
    }
};
