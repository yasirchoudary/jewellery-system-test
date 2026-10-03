<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class tbl_challan_details extends Model
{
    use HasFactory;

    protected $primaryKey = 'challan_details_id';

    protected $fillable = [
        'no',
        'qty',
        'weight_grams',
        'qty_unit',
        'challan_mst_id',
        'challan_type',
        'sell_quality_id',
        'sell_category_id',
        'gross_weight',
        'stone_weight',
        'net_weight',
        'purity_karat',
        'fine_weight',
        'tag_number',
        'challan_details_status',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'weight_grams' => 'decimal:3',
        'challan_details_status' => 'boolean',
    ];

    public static function isNoExists($no, $type, $fromDate, $toDate)
    {
        return tbl_challan_details::join('tbl_challan_msts', 'tbl_challan_msts.challan_mst_id', '=', 'tbl_challan_details.challan_mst_id')
            ->where('no', $no)
            ->where('tbl_challan_details.challan_type', $type)
            ->where('challan_details_status', true)
            ->whereBetween('challan_date', [$fromDate, $toDate])
            ->exists();
    }

    public function quality()
    {
        return $this->belongsTo(tbl_sell_quality::class, 'sell_quality_id', 'sell_quality_id');
    }

    public function category()
    {
        return $this->belongsTo(tbl_sell_quality_category::class, 'sell_category_id', 'sell_quality_category_id');
    }
}
