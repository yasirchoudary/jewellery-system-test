<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddJewelryFieldsToPurchaseAndSalesTables extends Migration
{
    public function up()
    {
        Schema::table('tbl_inward_details', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_inward_details', 'metal_type')) {
                $table->enum('metal_type', ['gold', 'silver'])->nullable()->after('inward_quality_id');
            }
            if (!Schema::hasColumn('tbl_inward_details', 'sell_quality_id')) {
                $table->unsignedInteger('sell_quality_id')->nullable()->after('metal_type');
                $table->foreign('sell_quality_id')->references('sell_quality_id')->on('tbl_sell_qualities')->nullOnDelete();
            }
            if (!Schema::hasColumn('tbl_inward_details', 'weight_grams')) {
                $table->decimal('weight_grams', 12, 3)->default(0)->after('qty');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tbl_inward_details MODIFY inward_quality_id INT UNSIGNED NULL');
        }

        Schema::table('tbl_challan_msts', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_challan_msts', 'weight_grams')) {
                $table->decimal('weight_grams', 12, 3)->default(0)->after('total_qty');
            }
        });

        Schema::table('tbl_invoice_msts', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_invoice_msts', 'weight_grams')) {
                $table->decimal('weight_grams', 12, 3)->default(0)->after('rate');
            }
            if (!Schema::hasColumn('tbl_invoice_msts', 'cost_amount')) {
                $table->decimal('cost_amount', 15, 2)->default(0)->after('weight_grams');
            }
            if (!Schema::hasColumn('tbl_invoice_msts', 'sold_amount')) {
                $table->decimal('sold_amount', 15, 2)->default(0)->after('cost_amount');
            }
            if (!Schema::hasColumn('tbl_invoice_msts', 'profit_amount')) {
                $table->decimal('profit_amount', 15, 2)->default(0)->after('sold_amount');
            }
        });
    }

    public function down()
    {
        Schema::table('tbl_inward_details', function (Blueprint $table) {
            $table->dropForeign(['sell_quality_id']);
            $table->dropColumn(['metal_type', 'sell_quality_id', 'weight_grams']);
        });

        Schema::table('tbl_challan_msts', function (Blueprint $table) {
            $table->dropColumn('weight_grams');
        });

        Schema::table('tbl_invoice_msts', function (Blueprint $table) {
            $table->dropColumn(['weight_grams', 'cost_amount', 'sold_amount', 'profit_amount']);
        });
    }
}
