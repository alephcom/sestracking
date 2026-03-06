<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipient_events', function (Blueprint $table) {
            // Date-range filtering on every dashboard/report query
            $table->index('event_at');
            // JOIN from email_recipients → recipient_events; the existing unique index
            // starts with sns_message_id so it cannot serve recipient_id lookups
            $table->index('recipient_id');
        });

        Schema::table('emails', function (Blueprint $table) {
            // Date-range filtering used throughout dashboard and reports
            $table->index('sent_at');
            // GROUP BY / ORDER BY in sendersReport
            $table->index('source');
        });

        // Full-text indexes for the activity list search; MySQL only
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE emails ADD FULLTEXT INDEX ft_emails_subject (subject)');
            DB::statement('ALTER TABLE email_recipients ADD FULLTEXT INDEX ft_email_recipients_address (address)');
        }
    }

    public function down(): void
    {
        Schema::table('recipient_events', function (Blueprint $table) {
            $table->dropIndex(['event_at']);
            $table->dropIndex(['recipient_id']);
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['source']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE emails DROP INDEX ft_emails_subject');
            DB::statement('ALTER TABLE email_recipients DROP INDEX ft_email_recipients_address');
        }
    }
};
