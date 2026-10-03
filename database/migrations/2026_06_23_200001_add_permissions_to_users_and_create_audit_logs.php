<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPermissionsToUsersAndCreateAuditLogs extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('permissions')->nullable()->after('role');
            });
        }

        if (!Schema::hasTable('tbl_audit_logs')) {
            Schema::create('tbl_audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('audit_log_id')->autoIncrement();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->string('user_name', 100)->nullable();
                $table->string('action', 20);
                $table->string('module', 50);
                $table->unsignedBigInteger('record_id')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('tbl_audit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
}
