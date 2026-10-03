<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropCashAmountFromTblLabJobsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('tbl_lab_jobs') || !Schema::hasColumn('tbl_lab_jobs', 'cash_amount')) {
            return;
        }

        // Laravel 8 SQLite dropColumn needs doctrine/dbal; skip when unavailable.
        if (Schema::getConnection()->getDriverName() === 'sqlite'
            && !class_exists(\Doctrine\DBAL\Driver\AbstractSQLiteDriver::class)) {
            return;
        }

        Schema::table('tbl_lab_jobs', function (Blueprint $table) {
            $table->dropColumn('cash_amount');
        });
    }

    public function down()
    {
        Schema::table('tbl_lab_jobs', function (Blueprint $table) {
            $table->decimal('cash_amount', 15, 2)->default(0)->after('base_price');
        });
    }
}
