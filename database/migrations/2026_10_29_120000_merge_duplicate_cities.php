<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Cities with the same name in the same country were created more than once (Termez, the train
    // stations, ...). The website's city dropdowns choke on duplicate names, so keep the oldest row of
    // each group, repoint everything that references the others to it, and delete the extras.
    public function up(): void
    {
        $duplicates = DB::select('
            SELECT id,
                   MIN(id) OVER (PARTITION BY LOWER(TRIM(name)), country_id) AS keep_id
            FROM cities
        ');

        $map = [];
        foreach ($duplicates as $row) {
            if ((int) $row->id !== (int) $row->keep_id) {
                $map[(int) $row->id] = (int) $row->keep_id;
            }
        }

        if (! $map) {
            return;
        }

        // Every column that stores a city id: real foreign keys to cities(id) plus the unconstrained *city_id ones.
        $columns = DB::select("
            SELECT DISTINCT c.table_name, c.column_name
            FROM information_schema.columns c
            JOIN information_schema.tables t
              ON t.table_schema = c.table_schema AND t.table_name = c.table_name AND t.table_type = 'BASE TABLE'
            WHERE c.table_schema = current_schema()
              AND c.table_name <> 'cities'
              AND c.column_name LIKE '%city_id'
              AND c.data_type IN ('integer', 'bigint', 'smallint')
        ");

        foreach ($map as $duplicateId => $keepId) {
            foreach ($columns as $column) {
                DB::table($column->table_name)
                    ->where($column->column_name, $duplicateId)
                    ->update([$column->column_name => $keepId]);
            }

            DB::table('cities')->where('id', $duplicateId)->delete();
        }
    }

    public function down(): void
    {
        // The removed duplicate rows can't be restored.
    }
};
