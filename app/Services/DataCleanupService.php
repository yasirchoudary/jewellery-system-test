<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataCleanupService
{
    /**
     * Delete all user-entered data entries from the database while keeping
     * logins (users table) and system/seeded master configuration intact.
     *
     * @return array
     */
    public static function clearUserData(): array
    {
        $report = [];
        $driver = DB::getDriverName();

        // 1. Disable Foreign Key Constraints
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        }

        // 2. Transactional & User-entered tables to completely truncate/delete
        $tablesToClear = [
            'tbl_invoice_details',
            'tbl_invoice_msts',
            'tbl_challan_details',
            'tbl_challan_msts',
            'tbl_inward_details',
            'tbl_inward_msts',
            'tbl_karigar_jobs',
            'tbl_karigars',
            'tbl_inventory_tags',
            'tbl_stock_ledger',
            'tbl_audit_logs',
            'tbl_customers',
            'tbl_vendors',
            'tbl_brokers',
            'tbl_expenses',
            'tbl_payment_details',
            'tbl_credits',
            'tbl_bank_details',
            'tbl_investor_expense_allocations',
            'tbl_investor_transactions',
            'tbl_investors',
            'tbl_lab_jobs',
            'tbl_lab_job_investors',
            'tbl_inward_qualities',
            'tbl_inward_quality_categories',
        ];

        foreach ($tablesToClear as $table) {
            if (Schema::hasTable($table)) {
                $deletedCount = DB::table($table)->count();
                DB::table($table)->delete();

                try {
                    if ($driver === 'sqlite') {
                        DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
                    } elseif ($driver === 'mysql') {
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                    }
                } catch (\Throwable $e) {
                    // Ignore sequence reset errors if table doesn't have autoincrement sequence
                }

                $report[$table] = "Cleared {$deletedCount} records";
            }
        }

        // 3. Reset tbl_metal_balances to 0 weight and 0 pieces for gold & silver
        if (Schema::hasTable('tbl_metal_balances')) {
            DB::table('tbl_metal_balances')->delete();
            DB::table('tbl_metal_balances')->insert([
                [
                    'metal_type' => 'gold',
                    'total_weight_grams' => 0,
                    'total_pieces' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'metal_type' => 'silver',
                    'total_weight_grams' => 0,
                    'total_pieces' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            $report['tbl_metal_balances'] = "Reset gold & silver balances to 0";
        }

        // 4. Clean tbl_sell_qualities and tbl_sell_quality_categories (keep standard seeded jewelry items)
        if (Schema::hasTable('tbl_sell_qualities') && Schema::hasTable('tbl_sell_quality_categories')) {
            $seededItems = ['Tops', 'Angothi', 'Kanty', 'Balian', 'Kady Set', 'Nail', 'Daddy', 'Pazeb', 'Tweeeez', 'Locket Set'];
            DB::table('tbl_sell_qualities')->whereNotIn('quality_name', $seededItems)->delete();

            // Ensure categories 'Gold Items' and 'Silver (Chandi) Items' exist and clean test categories
            DB::table('tbl_sell_quality_categories')->whereNotIn('sell_category_name', ['Gold Items', 'Silver (Chandi) Items'])->delete();

            // Get or create Gold category
            $goldCat = DB::table('tbl_sell_quality_categories')->where('sell_category_name', 'Gold Items')->first();
            if (!$goldCat) {
                $goldCatId = DB::table('tbl_sell_quality_categories')->insertGetId([
                    'sell_category_name' => 'Gold Items',
                    'metal_type' => 'gold',
                    'sell_quality_category_status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $goldCatId = $goldCat->sell_quality_category_id;
            }

            // Get or create Silver category
            $silverCat = DB::table('tbl_sell_quality_categories')->where('sell_category_name', 'Silver (Chandi) Items')->first();
            if (!$silverCat) {
                $silverCatId = DB::table('tbl_sell_quality_categories')->insertGetId([
                    'sell_category_name' => 'Silver (Chandi) Items',
                    'metal_type' => 'silver',
                    'sell_quality_category_status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $silverCatId = $silverCat->sell_quality_category_id;
            }

            // Ensure all standard gold items exist
            $goldItems = ['Tops', 'Angothi', 'Kanty', 'Balian', 'Kady Set'];
            foreach ($goldItems as $item) {
                $existing = DB::table('tbl_sell_qualities')->where('quality_name', $item)->first();
                if ($existing) {
                    DB::table('tbl_sell_qualities')->where('sell_quality_id', $existing->sell_quality_id)->update([
                        'sell_quality_category_id' => $goldCatId,
                        'sell_quality_status' => 1,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('tbl_sell_qualities')->insert([
                        'quality_name' => $item,
                        'sell_quality_category_id' => $goldCatId,
                        'sell_quality_status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Ensure all standard silver items exist
            $silverItems = ['Nail', 'Daddy', 'Pazeb', 'Tweeeez', 'Locket Set'];
            foreach ($silverItems as $item) {
                $existing = DB::table('tbl_sell_qualities')->where('quality_name', $item)->first();
                if ($existing) {
                    DB::table('tbl_sell_qualities')->where('sell_quality_id', $existing->sell_quality_id)->update([
                        'sell_quality_category_id' => $silverCatId,
                        'sell_quality_status' => 1,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('tbl_sell_qualities')->insert([
                        'quality_name' => $item,
                        'sell_quality_category_id' => $silverCatId,
                        'sell_quality_status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $report['tbl_sell_qualities'] = "Reset to 10 standard seeded items (5 Gold, 5 Silver)";
            $report['tbl_sell_quality_categories'] = "Reset to 2 standard categories (Gold Items, Silver Items)";
        }

        // 5. Clean tbl_expense_categories (keep standard seeded jewelry expense categories)
        if (Schema::hasTable('tbl_expense_categories')) {
            $standardExpenses = ['Electricity Bill', 'Rent', 'Food', 'Worker Salary', 'Refinery Cost', 'Other'];
            DB::table('tbl_expense_categories')->whereNotIn('expense_category', $standardExpenses)->delete();
            foreach ($standardExpenses as $cat) {
                $existing = DB::table('tbl_expense_categories')->where('expense_category', $cat)->first();
                if ($existing) {
                    DB::table('tbl_expense_categories')->where('expense_category_id', $existing->expense_category_id)->update([
                        'expense_category_status' => 1,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('tbl_expense_categories')->insert([
                        'expense_category' => $cat,
                        'expense_category_status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $report['tbl_expense_categories'] = "Reset to 6 standard seeded expense categories";
        }

        // 6. Users table: DO NOT DELETE ANY LOGINS
        $userCount = DB::table('users')->count();
        $report['users'] = "Preserved all {$userCount} users/logins intact";

        // 7. Re-enable Foreign Key Constraints
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } elseif ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
        }

        return $report;
    }
}
