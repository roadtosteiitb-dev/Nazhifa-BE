<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE users
            ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active'
                CHECK (status IN ('active','inactive')),
            ADD COLUMN IF NOT EXISTS phone VARCHAR(30)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS status');
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS phone');
    }
};
