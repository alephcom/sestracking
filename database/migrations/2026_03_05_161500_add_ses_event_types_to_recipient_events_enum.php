<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPE_ENUM_EXTENDED = [
        'send', 'delivery', 'bounce', 'complaint', 'reject',
        'rendering_failure', 'delivery_delay', 'subscription', 'open', 'click', 'unknown',
    ];

    private const TYPE_ENUM_ORIGINAL = [
        'send', 'delivery', 'bounce', 'complaint', 'reject',
        'rendering_failure', 'open', 'click',
    ];

    /**
     * Run the migrations.
     * Extend recipient_events.type ENUM to include delivery_delay, subscription, and unknown.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE recipient_events MODIFY COLUMN type ENUM(\'' . implode("','", self::TYPE_ENUM_EXTENDED) . '\') NOT NULL');
        }

        if ($driver === 'sqlite') {
            Schema::dropIfExists('recipient_events_new');
            $typeCheck = implode("','", self::TYPE_ENUM_EXTENDED);
            DB::statement("CREATE TABLE recipient_events_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recipient_id INTEGER NOT NULL,
                sns_message_id VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL CHECK (type IN ('{$typeCheck}')),
                event_at DATETIME NOT NULL,
                payload TEXT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id)
            )");
            DB::statement('CREATE UNIQUE INDEX recipient_events_new_type_unique ON recipient_events_new (sns_message_id, recipient_id, type)');
            DB::statement('INSERT INTO recipient_events_new (id, recipient_id, sns_message_id, type, event_at, payload, created_at, updated_at) SELECT id, recipient_id, sns_message_id, type, event_at, payload, created_at, updated_at FROM recipient_events');
            Schema::drop('recipient_events');
            DB::statement('ALTER TABLE recipient_events_new RENAME TO recipient_events');
            DB::statement('DROP INDEX recipient_events_new_type_unique');
            DB::statement('CREATE UNIQUE INDEX recipient_events_sns_message_id_recipient_id_type_unique ON recipient_events (sns_message_id, recipient_id, type)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE recipient_events MODIFY COLUMN type ENUM(\'' . implode("','", self::TYPE_ENUM_ORIGINAL) . '\') NOT NULL');
        }

        if ($driver === 'sqlite') {
            $typeCheck = implode("','", self::TYPE_ENUM_ORIGINAL);
            DB::statement("CREATE TABLE recipient_events_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recipient_id INTEGER NOT NULL,
                sns_message_id VARCHAR(255) NOT NULL,
                type VARCHAR(255) NOT NULL CHECK (type IN ('{$typeCheck}')),
                event_at DATETIME NOT NULL,
                payload TEXT,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id)
            )");
            DB::statement('CREATE UNIQUE INDEX recipient_events_new_type_unique ON recipient_events_new (sns_message_id, recipient_id, type)');
            DB::statement("INSERT INTO recipient_events_new (id, recipient_id, sns_message_id, type, event_at, payload, created_at, updated_at) SELECT id, recipient_id, sns_message_id, type, event_at, payload, created_at, updated_at FROM recipient_events WHERE type IN ('" . implode("','", self::TYPE_ENUM_ORIGINAL) . "')");
            Schema::drop('recipient_events');
            DB::statement('ALTER TABLE recipient_events_new RENAME TO recipient_events');
        }
    }
};
