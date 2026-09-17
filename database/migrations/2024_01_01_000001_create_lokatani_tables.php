<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgrouting');

        // =============================================
        // TABLE: users
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS users (
                id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                full_name     VARCHAR(255) NOT NULL,
                email         VARCHAR(255) UNIQUE NOT NULL,
                password      VARCHAR(255) NOT NULL,
                user_type     VARCHAR(20)  NOT NULL DEFAULT \'buyer\'
                                CHECK (user_type IN (\'guest\',\'buyer\',\'owner\',\'admin\')),
                photo         TEXT,
                remember_token VARCHAR(100),
                created_at    TIMESTAMP,
                updated_at    TIMESTAMP
            )
        ');

        // =============================================
        // TABLE: lands
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS lands (
                id                UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                name              VARCHAR(255) NOT NULL,
                location          VARCHAR(500) NOT NULL,
                price             BIGINT,
                is_for_sale       BOOLEAN NOT NULL DEFAULT TRUE,
                type              VARCHAR(20) CHECK (type IN (\'house\',\'apartment\')),
                status            VARCHAR(20) NOT NULL DEFAULT \'Pending\'
                                    CHECK (status IN (\'Pending\',\'Approved\',\'Rejected\',\'Sold\',\'Archived\')),
                owner_id          UUID REFERENCES users(id) ON DELETE SET NULL,
                description       TEXT,
                image             TEXT,
                images            TEXT[],
                floors            INT,
                bedrooms          INT,
                bathrooms         INT,
                electricity       INT,
                certificate       VARCHAR(20),
                certificate_image TEXT,
                garage            VARCHAR(100),
                unit_floor        INT,
                unit_type         VARCHAR(20),
                furnished         VARCHAR(20) CHECK (furnished IN (\'furnished\',\'semi\',\'unfurnished\')),
                land_area         VARCHAR(50),
                building_area     VARCHAR(50),
                facilities        TEXT[],
                views             INT NOT NULL DEFAULT 0,
                favorites         INT NOT NULL DEFAULT 0,
                inquiries_count   INT NOT NULL DEFAULT 0,
                geom              GEOMETRY(Point, 4326),
                rejection_reason  TEXT,
                created_at        TIMESTAMP,
                updated_at        TIMESTAMP
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_lands_geom   ON lands USING GIST(geom)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_lands_status ON lands(status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_lands_owner  ON lands(owner_id)');

        // =============================================
        // TABLE: conversations
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS conversations (
                id                UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                property_id       UUID REFERENCES lands(id) ON DELETE CASCADE,
                buyer_id          UUID REFERENCES users(id) ON DELETE CASCADE,
                owner_id          UUID REFERENCES users(id) ON DELETE CASCADE,
                last_message      TEXT,
                last_message_time VARCHAR(10),
                unread_buyer      INT NOT NULL DEFAULT 0,
                unread_owner      INT NOT NULL DEFAULT 0,
                created_at        TIMESTAMP,
                updated_at        TIMESTAMP,
                UNIQUE(property_id, buyer_id, owner_id)
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_conversations_buyer  ON conversations(buyer_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_conversations_owner  ON conversations(owner_id)');

        // =============================================
        // TABLE: messages
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS messages (
                id               UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                conversation_id  UUID REFERENCES conversations(id) ON DELETE CASCADE,
                sender_id        UUID REFERENCES users(id) ON DELETE SET NULL,
                sender_role      VARCHAR(10) NOT NULL CHECK (sender_role IN (\'buyer\',\'owner\')),
                sender_name      VARCHAR(255),
                text             TEXT NOT NULL,
                status           VARCHAR(10) NOT NULL DEFAULT \'sent\' CHECK (status IN (\'sent\',\'read\')),
                created_at       TIMESTAMP,
                updated_at       TIMESTAMP
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id)');

        // =============================================
        // TABLE: notifications
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS notifications (
                id            UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                property_id   UUID REFERENCES lands(id) ON DELETE CASCADE,
                property_name VARCHAR(255),
                type          VARCHAR(20) NOT NULL
                                CHECK (type IN (\'submitted\',\'approved\',\'rejected\',\'archived\',\'sold\')),
                owner_id      UUID REFERENCES users(id) ON DELETE CASCADE,
                reason        TEXT,
                is_read       BOOLEAN NOT NULL DEFAULT FALSE,
                created_at    TIMESTAMP,
                updated_at    TIMESTAMP
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_notifications_owner ON notifications(owner_id)');

        // =============================================
        // TABLE: favorites
        // =============================================
        DB::statement('
            CREATE TABLE IF NOT EXISTS favorites (
                id          UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
                user_id     UUID REFERENCES users(id) ON DELETE CASCADE,
                property_id UUID REFERENCES lands(id) ON DELETE CASCADE,
                created_at  TIMESTAMP,
                updated_at  TIMESTAMP,
                UNIQUE(user_id, property_id)
            )
        ');

        // Auto-update updated_at trigger
        DB::statement('
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ language \'plpgsql\'
        ');

        foreach (['users', 'lands', 'conversations', 'messages', 'notifications'] as $table) {
            DB::statement("
                CREATE TRIGGER update_{$table}_updated_at
                BEFORE UPDATE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()
            ");
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS favorites CASCADE');
        DB::statement('DROP TABLE IF EXISTS notifications CASCADE');
        DB::statement('DROP TABLE IF EXISTS messages CASCADE');
        DB::statement('DROP TABLE IF EXISTS conversations CASCADE');
        DB::statement('DROP TABLE IF EXISTS lands CASCADE');
        DB::statement('DROP TABLE IF EXISTS users CASCADE');
        DB::statement('DROP EXTENSION IF EXISTS pgrouting');
        DB::statement('DROP EXTENSION IF EXISTS postgis CASCADE');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp"');
    }
};
