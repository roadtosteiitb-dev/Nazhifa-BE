<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE complaints ADD COLUMN IF NOT EXISTS image TEXT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE complaints DROP COLUMN IF EXISTS image');
    }
};
