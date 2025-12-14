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
        // Only run if role column exists (for existing installations)
        // Fresh installs already have super_admin from create_users_table migration
        if (Schema::hasColumn('users', 'role')) {
            // Add super_admin boolean column if it doesn't exist
            if (!Schema::hasColumn('users', 'super_admin')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->boolean('super_admin')->default(false)->after('email');
                });
            }
            
            // Migrate existing super_admin role users to the flag
            \DB::table('users')->where('role', 'super_admin')->update(['super_admin' => true]);
            
            // Drop the role column
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Re-add role column
            $table->enum('role', ['admin', 'user'])->default('user')->after('email');
        });
        
        // Migrate super_admin flag back to role
        \DB::table('users')->where('super_admin', true)->update(['role' => 'admin']);
        \DB::table('users')->where('super_admin', false)->update(['role' => 'user']);
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('super_admin');
        });
    }
};
