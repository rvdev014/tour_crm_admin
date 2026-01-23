<?php

use App\Models\HotelPeriod;
use App\Models\HotelRoomType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
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
        // 1. Add the new column (nullable initially so we can populate it)
        Schema::table('hotel_room_types', function (Blueprint $table) {
            $table->foreignId('hotel_period_id')->nullable()->after('hotel_id')
                ->constrained('hotel_periods')->onDelete('cascade');
        });
        
        // 2. Data Migration Logic
        // We find all existing prices and join them with periods that share the same season_type
        /** @var Collection<HotelRoomType> $hotelRoomTypes */
        $hotelRoomTypes = DB::table('hotel_room_types')->get();
        
        foreach ($hotelRoomTypes as $hotelRoomType) {
            /** @var Collection<HotelPeriod> $matchingPeriods */
            $matchingPeriods = DB::table('hotel_periods')
                ->where('hotel_id', $hotelRoomType->hotel_id)
                ->where('season_type', $hotelRoomType->season_type)
                ->get();
            
            foreach ($matchingPeriods as $index => $period) {
                if ($index === 0) {
                    // Update the first record we found to point to its specific period
                    DB::table('hotel_room_types')
                        ->where('id', $hotelRoomType->id)
                        ->update(['hotel_period_id' => $period->id]);
                } else {
                    // Create NEW price records for additional periods of the same type
                    // This ensures every period now has a dedicated price entry
                    DB::table('hotel_room_types')->insert([
                        'hotel_id' => $hotelRoomType->hotel_id,
                        'room_type_id' => $hotelRoomType->room_type_id,
                        'hotel_period_id' => $period->id,
                        'price' => $hotelRoomType->price,
                        'price_foreign' => $hotelRoomType->price_foreign,
                        // Copy other relevant columns if they exist
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_room_types', function (Blueprint $table) {
            $table->dropForeign(['hotel_period_id']);
            $table->dropColumn('hotel_period_id');
        });
    }
};
