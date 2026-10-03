<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SellQualityCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('tbl_sell_quality_categories')->insert([
            [
                'sell_category_name' => 'Gold Items',
                'metal_type' => 'gold',
                'sell_quality_category_status' => 1
            ],
            [
                'sell_category_name' => 'Silver (Chandi) Items',
                'metal_type' => 'silver',
                'sell_quality_category_status' => 1
            ],
        ]);
    }
}
