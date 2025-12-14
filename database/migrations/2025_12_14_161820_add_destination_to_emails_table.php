<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('emails')) {
            if (!Schema::hasColumn('emails', 'destination')) {
                Schema::table('emails', function (Blueprint $table) {
                    $table->text('destination')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('emails')) {
            if (Schema::hasColumn('emails', 'destination')) {
                Schema::table('emails', function (Blueprint $table) {
                    $table->dropColumn('destination');
                });
            }
        }
    }
};
