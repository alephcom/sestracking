<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('ses_suppression_auto_push_enabled')->default(false);
            $table->boolean('ses_suppression_push_complaints')->default(true);
            $table->boolean('ses_suppression_push_soft_bounces')->default(false);
            $table->string('ses_aws_access_key_id')->nullable();
            $table->text('ses_aws_secret_access_key')->nullable();
            $table->string('ses_aws_default_region', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'ses_suppression_auto_push_enabled',
                'ses_suppression_push_complaints',
                'ses_suppression_push_soft_bounces',
                'ses_aws_access_key_id',
                'ses_aws_secret_access_key',
                'ses_aws_default_region',
            ]);
        });
    }
};
