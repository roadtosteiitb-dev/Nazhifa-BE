<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lands DROP CONSTRAINT IF EXISTS lands_type_check');
        DB::statement("ALTER TABLE lands ADD CONSTRAINT lands_type_check CHECK (type IN ('house','apartment','villa'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE lands SET type = 'house' WHERE type = 'villa'");
        DB::statement('ALTER TABLE lands DROP CONSTRAINT IF EXISTS lands_type_check');
        DB::statement("ALTER TABLE lands ADD CONSTRAINT lands_type_check CHECK (type IN ('house','apartment'))");
    }
};
