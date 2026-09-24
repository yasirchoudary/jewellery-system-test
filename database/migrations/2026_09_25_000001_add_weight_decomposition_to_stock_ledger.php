<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Stock Ledger
        Schema::table('tbl_stock_ledger', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_stock_ledger', 'gross_weight')) {
                $table->decimal('gross_weight', 14, 3)->default(0)->after('transaction_type');
            }
            if (!Schema::hasColumn('tbl_stock_ledger', 'stone_weight')) {
                $table->decimal('stone_weight', 14, 3)->default(0)->after('gross_weight');
            }
            if (!Schema::hasColumn('tbl_stock_ledger', 'net_weight')) {
                $table->decimal('net_weight', 14, 3)->default(0)->after('stone_weight');
            }
            if (!Schema::hasColumn('tbl_stock_ledger', 'purity_karat')) {
                $table->decimal('purity_karat', 5, 2)->default(22.0)->after('net_weight');
            }
            if (!Schema::hasColumn('tbl_stock_ledger', 'fine_weight')) {
                $table->decimal('fine_weight', 14, 3)->default(0)->after('purity_karat');
            }
        });

        // 2. Inward Details (Purchases)
        Schema::table('tbl_inward_details', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_inward_details', 'gross_weight')) {
                $table->decimal('gross_weight', 14, 3)->default(0)->after('weight_grams');
            }
            if (!Schema::hasColumn('tbl_inward_details', 'stone_weight')) {
                $table->decimal('stone_weight', 14, 3)->default(0)->after('gross_weight');
            }
            if (!Schema::hasColumn('tbl_inward_details', 'net_weight')) {
                $table->decimal('net_weight', 14, 3)->default(0)->after('stone_weight');
            }
            if (!Schema::hasColumn('tbl_inward_details', 'purity_karat')) {
                $table->decimal('purity_karat', 5, 2)->default(22.0)->after('net_weight');
            }
            if (!Schema::hasColumn('tbl_inward_details', 'fine_weight')) {
                $table->decimal('fine_weight', 14, 3)->default(0)->after('purity_karat');
            }
        });

        // 3. Challan Details (Sales Bills)
        Schema::table('tbl_challan_details', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_challan_details', 'gross_weight')) {
                $table->decimal('gross_weight', 14, 3)->default(0)->after('weight_grams');
            }
            if (!Schema::hasColumn('tbl_challan_details', 'stone_weight')) {
                $table->decimal('stone_weight', 14, 3)->default(0)->after('gross_weight');
            }
            if (!Schema::hasColumn('tbl_challan_details', 'net_weight')) {
                $table->decimal('net_weight', 14, 3)->default(0)->after('stone_weight');
            }
            if (!Schema::hasColumn('tbl_challan_details', 'purity_karat')) {
                $table->decimal('purity_karat', 5, 2)->default(22.0)->after('net_weight');
            }
            if (!Schema::hasColumn('tbl_challan_details', 'fine_weight')) {
                $table->decimal('fine_weight', 14, 3)->default(0)->after('purity_karat');
            }
        });

        // 4. Invoice Details
        if (Schema::hasTable('tbl_invoice_details')) {
            Schema::table('tbl_invoice_details', function (Blueprint $table) {
                if (!Schema::hasColumn('tbl_invoice_details', 'gross_weight')) {
                    $table->decimal('gross_weight', 14, 3)->default(0)->after('weight_grams');
                }
                if (!Schema::hasColumn('tbl_invoice_details', 'stone_weight')) {
                    $table->decimal('stone_weight', 14, 3)->default(0)->after('gross_weight');
                }
                if (!Schema::hasColumn('tbl_invoice_details', 'net_weight')) {
                    $table->decimal('net_weight', 14, 3)->default(0)->after('stone_weight');
                }
                if (!Schema::hasColumn('tbl_invoice_details', 'purity_karat')) {
                    $table->decimal('purity_karat', 5, 2)->default(22.0)->after('net_weight');
                }
                if (!Schema::hasColumn('tbl_invoice_details', 'fine_weight')) {
                    $table->decimal('fine_weight', 14, 3)->default(0)->after('purity_karat');
                }
            });
        }

        // 5. Karigar Jobs
        Schema::table('tbl_karigar_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_karigar_jobs', 'purity_karat')) {
                $table->decimal('purity_karat', 5, 2)->default(22.0)->after('issued_weight_grams');
            }
            if (!Schema::hasColumn('tbl_karigar_jobs', 'issued_fine_weight')) {
                $table->decimal('issued_fine_weight', 14, 3)->default(0)->after('purity_karat');
            }
            if (!Schema::hasColumn('tbl_karigar_jobs', 'returned_fine_weight')) {
                $table->decimal('returned_fine_weight', 14, 3)->default(0)->after('returned_weight_grams');
            }
        });
    }

    public function down()
    {
        // Safe rollback
        Schema::table('tbl_stock_ledger', function (Blueprint $table) {
            $table->dropColumn(['gross_weight', 'stone_weight', 'net_weight', 'purity_karat', 'fine_weight']);
        });

        Schema::table('tbl_inward_details', function (Blueprint $table) {
            $table->dropColumn(['gross_weight', 'stone_weight', 'net_weight', 'purity_karat', 'fine_weight']);
        });

        Schema::table('tbl_challan_details', function (Blueprint $table) {
            $table->dropColumn(['gross_weight', 'stone_weight', 'net_weight', 'purity_karat', 'fine_weight']);
        });

        if (Schema::hasTable('tbl_invoice_details')) {
            Schema::table('tbl_invoice_details', function (Blueprint $table) {
                $table->dropColumn(['gross_weight', 'stone_weight', 'net_weight', 'purity_karat', 'fine_weight']);
            });
        }

        Schema::table('tbl_karigar_jobs', function (Blueprint $table) {
            $table->dropColumn(['purity_karat', 'issued_fine_weight', 'returned_fine_weight']);
        });
    }
};
