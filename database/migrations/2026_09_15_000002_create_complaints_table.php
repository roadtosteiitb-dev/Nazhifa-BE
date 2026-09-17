<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS complaints (
                id           UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                reporter_id  UUID REFERENCES users(id) ON DELETE SET NULL,
                property_id  UUID REFERENCES lands(id) ON DELETE SET NULL,
                category     VARCHAR(50) NOT NULL,
                message      TEXT NOT NULL,
                status       VARCHAR(20) NOT NULL DEFAULT \'open\'
                                CHECK (status IN (\'open\',\'in_progress\',\'resolved\')),
                created_at   TIMESTAMP,
                updated_at   TIMESTAMP
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_complaints_reporter ON complaints(reporter_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_complaints_status   ON complaints(status)');

        DB::statement("
            CREATE TRIGGER update_complaints_updated_at
            BEFORE UPDATE ON complaints
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS complaints CASCADE');
    }
};
