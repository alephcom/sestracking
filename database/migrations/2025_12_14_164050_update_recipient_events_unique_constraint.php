<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('recipient_events')) {
            // Drop the old unique constraint on sns_message_id alone
            // We'll create a composite unique constraint instead
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                // Check if the old unique index exists
                $indexes = DB::select("SHOW INDEXES FROM `recipient_events` WHERE Key_name = 'recipient_events_sns_message_id_unique'");
                if (!empty($indexes)) {
                    DB::statement('ALTER TABLE `recipient_events` DROP INDEX `recipient_events_sns_message_id_unique`');
                }
                
                // Add composite unique constraint on (sns_message_id, recipient_id, type)
                // This allows the same SNS message to create events for multiple recipients
                $compositeIndexes = DB::select("SHOW INDEXES FROM `recipient_events` WHERE Key_name = 'recipient_events_sns_message_id_recipient_id_type_unique'");
                if (empty($compositeIndexes)) {
                    DB::statement('ALTER TABLE `recipient_events` ADD UNIQUE KEY `recipient_events_sns_message_id_recipient_id_type_unique` (`sns_message_id`, `recipient_id`, `type`)');
                }
            } else {
                // For SQLite and other databases, use Schema builder
                Schema::table('recipient_events', function (Blueprint $table) {
                    $table->dropUnique(['sns_message_id']);
                    $table->unique(['sns_message_id', 'recipient_id', 'type'], 'recipient_events_sns_message_id_recipient_id_type_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('recipient_events')) {
            $driver = DB::getDriverName();
            
            if ($driver === 'mysql') {
                // Drop composite unique constraint
                $compositeIndexes = DB::select("SHOW INDEXES FROM `recipient_events` WHERE Key_name = 'recipient_events_sns_message_id_recipient_id_type_unique'");
                if (!empty($compositeIndexes)) {
                    DB::statement('ALTER TABLE `recipient_events` DROP INDEX `recipient_events_sns_message_id_recipient_id_type_unique`');
                }
                
                // Re-add old unique constraint on sns_message_id alone
                $indexes = DB::select("SHOW INDEXES FROM `recipient_events` WHERE Key_name = 'recipient_events_sns_message_id_unique'");
                if (empty($indexes)) {
                    DB::statement('ALTER TABLE `recipient_events` ADD UNIQUE KEY `recipient_events_sns_message_id_unique` (`sns_message_id`)');
                }
            } else {
                Schema::table('recipient_events', function (Blueprint $table) {
                    $table->dropUnique(['sns_message_id', 'recipient_id', 'type']);
                    $table->unique(['sns_message_id']);
                });
            }
        }
    }
};
