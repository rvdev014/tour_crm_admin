<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add the new columns to the table
        Schema::table('hotel_room_types', function(Blueprint $table) {
            $table->integer('year')->nullable()->after('season_type');
        });

        // 2. Populate the new fields by joining with hotel_periods
        // Using chunk() to keep memory usage low
        DB::table('hotel_room_types')
            ->join('hotel_periods', 'hotel_room_types.hotel_period_id', '=', 'hotel_periods.id')
            ->select('hotel_room_types.id', 'hotel_periods.season_type', 'hotel_periods.start_date')
            ->orderBy('hotel_room_types.id')
            ->chunk(500, function($roomTypes) {
                foreach ($roomTypes as $rt) {
                    DB::table('hotel_room_types')
                        ->where('id', $rt->id)
                        ->update([
                            'season_type' => $rt->season_type,
                            'year' => Carbon::parse($rt->start_date)->year,
                        ]);
                }
            });

        // 3. Identify the IDs of the unique records we want to keep
        // Grouping by hotel, room type, season, and year
        $keptIds = DB::table('hotel_room_types')
            ->select(DB::raw('MIN(id) as id'))
            ->groupBy('hotel_id', 'room_type_id', 'season_type', 'year')
            ->pluck('id');

        // 4. Delete the redundant duplicate records
        DB::table('hotel_room_types')
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_room_types', function (Blueprint $table) {
            $table->dropColumn(['year']);
        });
    }
};
