<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ses_suppressed_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->string('reason', 64);
            $table->timestamp('last_update_time')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['project_id', 'email']);
            $table->index(['project_id', 'synced_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('ses_suppression_list_synced_at')->nullable()->after('ses_aws_default_region');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ses_suppression_list_synced_at');
        });

        Schema::dropIfExists('ses_suppressed_destinations');
    }
};
