<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->integer('sort_order')->default(999);
        });

        // Define the exact names and their target sort orders based on your screenshot
        $roomOrders = [
            'Standard Single Room'      => 1,
            'Standard Double/Twin Room' => 2,
            'Triple'                    => 3,
            'Suite Single Use'          => 4,
            'Suite Double Use'          => 5,
        ];

        // Loop through and update each specific room type
        foreach ($roomOrders as $name => $order) {
            DB::table('room_types')
                ->where('name', $name)
                ->update(['sort_order' => $order]);
        }

        // Optional: Ensure all other room types default to 999 so they stay at the bottom
        DB::table('room_types')
            ->whereNotIn('name', array_keys($roomOrders))
            ->update(['sort_order' => 999]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // If rolled back, reset the specific rooms back to 999
        $names = [
            'Standard Single Room',
            'Standard Double/Twin Room',
            'Triple',
            'Suite Single Use',
            'Suite Double Use',
        ];

        DB::table('room_types')
            ->whereIn('name', $names)
            ->update(['sort_order' => 999]);

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
