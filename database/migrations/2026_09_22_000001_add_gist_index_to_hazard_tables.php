<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a GiST spatial index to the geom column of every hazard table.
 * These tables were bulk-imported from shapefiles (not Eloquent models),
 * so they never got the index PostGIS needs to make bbox/viewport queries
 * fast. Without it, every "give me polygons near this map region" query
 * has to sequentially scan the whole table (up to ~23k rows for
 * extremeweather) instead of using an index to jump straight to nearby rows.
 */
return new class extends Migration
{
    private array $tables = [
        'flood',
        'landslide',
        'drought',
        'eruption',
        'liquefaction',
        'extremeweather',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("CREATE INDEX IF NOT EXISTS idx_{$table}_geom ON \"{$table}\" USING GIST (geom)");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("DROP INDEX IF EXISTS idx_{$table}_geom");
        }
    }
};
